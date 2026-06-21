<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Broadcasts\BroadcastException;
use App\Broadcasts\BroadcastLifecycleService;
use App\Broadcasts\BroadcastRepository;
use App\Broadcasts\BroadcastState;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Jobs\JobHandler;
use App\Jobs\JobHandlerContext;
use App\Jobs\JobIntent;
use App\Jobs\JobRecord;
use App\Jobs\JobRepository;
use App\Jobs\JobState;
use App\Support\PrefixedUlid;
use App\System\Activity\ActivityEventService;
use App\System\Event\EventPublisher;
use App\System\State\StateTransitionService;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

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
            'plan', 'verify', 'prune', 'trigger', 'rotate_token' => 2,
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
                'rotate_token' => $this->handleRotateToken($command, $job, $context, $broadcastId),
                default => throw BroadcastException::withCode('broadcast_action_unsupported', 'Unsupported broadcast action.'),
            };

            $command->resultJson = json_encode($result, JSON_THROW_ON_ERROR);
            $this->commands->save($command);

            $job->progressCurrent = $job->progressTotal;
            $job->progressPercent = 100.0;
            $job->progressLabel = 'Broadcast ' . $action . ' complete';
            $job->finishedAt = DateTime::now(Timezone::UTC);
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
    private function handleRotateToken(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $broadcastId,
    ): array {
        $context->progress($job, 1, 2, 'Rotating podcast token');
        $result = $this->lifecycle->rotateToken($broadcastId);
        $this->activity->broadcastTokenRotated($command, $job, $broadcastId, $result->toArray());
        $context->progress($job, 2, 2, 'Podcast token rotated');

        return ['token' => $result->toArray()];
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
