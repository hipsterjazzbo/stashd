<?php

declare(strict_types=1);

namespace App\Services\Job\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandState;
use App\Domain\Job\JobIntent;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Services\Activity\ActivityEventService;
use App\Services\Event\EventPublisher;
use App\Services\Job\JobHandler;
use App\Services\Job\JobHandlerContext;
use App\Services\Stash\StashFromPreflightService;
use App\Services\State\StateTransitionService;

final readonly class CreateFromPreflightJobHandler implements JobHandler
{
    public function __construct(
        private StashFromPreflightService $stashFromPreflight,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::CreateFromPreflight;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);
        $context->progress($job, 0, 1, 'Creating stash from preflight');

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        $preflightCommandId = PrefixedUlid::parse((string) ($payload['preflight_command_id'] ?? ''));
        $result = $this->stashFromPreflight->commit($preflightCommandId, $payload);

        $command->resultJson = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
        $command->targetType = 'stash';
        $command->targetId = $result->stashId;
        $this->commands->save($command);

        $job->progressCurrent = 1;
        $job->progressTotal = 1;
        $job->progressPercent = 100.0;
        $job->progressLabel = 'Stash created from preflight';
        $job->finishedAt = RecordTimestamps::now();
        $this->jobs->save($job);
        $context->progress($job, 1, 1, $job->progressLabel);

        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->stashCreatedFromPreflight($command, $job, $result);
        $this->publisher->jobCompleted($job);
        $this->activity->commandCompleted($command);
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Create-from-preflight job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new \RuntimeException('Create-from-preflight command not found.');
    }
}
