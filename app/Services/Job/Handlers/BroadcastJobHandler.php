<?php

declare(strict_types=1);

namespace App\Services\Job\Handlers;

use App\Domain\Broadcast\BroadcastException;
use App\Domain\Broadcast\BroadcastState;
use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandState;
use App\Domain\Job\JobIntent;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\BroadcastRepository;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Services\Activity\ActivityEventService;
use App\Services\Broadcast\BroadcastLifecycleService;
use App\Services\Event\EventPublisher;
use App\Services\Job\JobHandler;
use App\Services\Job\JobHandlerContext;
use App\Services\State\StateTransitionService;

final readonly class BroadcastJobHandler implements JobHandler
{
    public function __construct(
        private BroadcastLifecycleService $lifecycle,
        private BroadcastRepository $broadcasts,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::Broadcast;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        $broadcastId = PrefixedUlid::parse((string) ($payload['broadcast_id'] ?? ''));
        $action = (string) ($payload['action'] ?? 'rebuild');

        $broadcast = $this->broadcasts->find($broadcastId);

        $job->progressTotal = match ($action) {
            'plan', 'verify', 'prune', 'trigger' => 2,
            'rebuild' => 4,
            default => 1,
        };
        $this->jobs->save($job);

        try {
            $result = match ($action) {
                'plan' => $this->handlePlan($command, $job, $context, $broadcastId),
                'rebuild' => $this->handleRebuild($command, $job, $context, $broadcastId),
                'verify' => $this->handleVerify($command, $job, $context, $broadcastId),
                'prune' => $this->handlePrune($command, $job, $context, $broadcastId),
                'trigger' => $this->handleTrigger($command, $job, $context, $broadcastId),
                default => throw BroadcastException::withCode('broadcast_action_unsupported', 'Unsupported broadcast action.'),
            };

            $command->resultJson = json_encode($result, JSON_THROW_ON_ERROR);
            $this->commands->save($command);

            $job->progressCurrent = $job->progressTotal;
            $job->progressPercent = 100.0;
            $job->progressLabel = 'Broadcast ' . $action . ' complete';
            $job->finishedAt = RecordTimestamps::now();
            $this->jobs->save($job);
            $context->progress($job, $job->progressTotal, $job->progressTotal, $job->progressLabel);

            $this->transitions->transitionJob($job, JobState::Ready);
            $this->transitions->transitionCommand($command, CommandState::Completed);
            $this->activity->commandCompleted($command);
            $this->publisher->jobCompleted($job);
        } catch (BroadcastException $exception) {
            if ($broadcast !== null && $broadcast->state === BroadcastState::Processing) {
                $broadcast->lastError = $exception->errorCode;
                $this->broadcasts->save($broadcast);

                if ($broadcast->state->canTransitionTo(BroadcastState::Failed)) {
                    $this->transitions->transitionBroadcast($broadcast, BroadcastState::Failed);
                }
            }

            $this->activity->broadcastFailed($command, $job, $broadcastId, $exception->errorCode, $exception->getMessage());

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function handlePlan(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $broadcastId,
    ): array {
        $context->progress($job, 1, 2, 'Planning broadcast');
        $plan = $this->lifecycle->plan($broadcastId);
        $this->activity->broadcastPlanned($command, $job, $broadcastId, $plan->toArray());
        $context->progress($job, 2, 2, 'Broadcast plan ready');

        return ['plan' => $plan->toArray()];
    }

    /** @return array<string, mixed> */
    private function handleRebuild(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $broadcastId,
    ): array {
        $context->progress($job, 1, 4, 'Planning broadcast rebuild');
        $this->activity->broadcastRebuildStarted($command, $job, $broadcastId);

        $result = $this->lifecycle->rebuild($broadcastId);

        $context->progress($job, 3, 4, 'Broadcast published');
        $this->activity->broadcastPublished($command, $job, $broadcastId, $result->publish ?? []);

        $context->progress($job, 4, 4, 'Broadcast verified');
        $this->activity->broadcastVerified($command, $job, $broadcastId, $result->verify ?? []);

        if (($result->verify['stale_count'] ?? 0) > 0) {
            $this->activity->broadcastStale($command, $job, $broadcastId, $result->verify ?? []);
        }

        if ($result->trigger !== null) {
            $this->recordTriggerActivity($command, $job, $broadcastId, $result->trigger);
        }

        return $result->toArray();
    }

    /** @return array<string, mixed> */
    private function handleTrigger(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $broadcastId,
    ): array {
        $context->progress($job, 1, 2, 'Triggering media server scan');
        $trigger = $this->lifecycle->trigger($broadcastId);
        $this->recordTriggerActivity($command, $job, $broadcastId, $trigger->toArray());
        $context->progress($job, 2, 2, 'Broadcast trigger complete');

        return ['trigger' => $trigger->toArray()];
    }

    /** @param array<string, mixed> $trigger */
    private function recordTriggerActivity(
        CommandRecord $command,
        JobRecord $job,
        PrefixedUlid $broadcastId,
        array $trigger,
    ): void {
        if (($trigger['failure_count'] ?? 0) > 0) {
            $this->activity->broadcastTriggerFailed($command, $job, $broadcastId, $trigger);
        } elseif (($trigger['success_count'] ?? 0) > 0) {
            $this->activity->broadcastTriggerSucceeded($command, $job, $broadcastId, $trigger);
        }
    }

    /** @return array<string, mixed> */
    private function handleVerify(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $broadcastId,
    ): array {
        $context->progress($job, 1, 2, 'Verifying broadcast');
        $verify = $this->lifecycle->verify($broadcastId);
        $this->activity->broadcastVerified($command, $job, $broadcastId, $verify->toArray());

        if ($verify->staleCount > 0) {
            $this->activity->broadcastStale($command, $job, $broadcastId, $verify->toArray());
        }

        $context->progress($job, 2, 2, 'Broadcast verification complete');

        return ['verify' => $verify->toArray()];
    }

    /** @return array<string, mixed> */
    private function handlePrune(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $broadcastId,
    ): array {
        $context->progress($job, 1, 2, 'Pruning stale broadcast files');
        $prune = $this->lifecycle->prune($broadcastId);
        $this->activity->broadcastPruned($command, $job, $broadcastId, $prune->toArray());
        $context->progress($job, 2, 2, 'Broadcast prune complete');

        return ['prune' => $prune->toArray()];
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Broadcast job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new \RuntimeException('Broadcast command not found.');
    }
}
