<?php

declare(strict_types=1);

namespace App\Services\Job\Handlers;

use App\Config\StashdConfig;
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
use App\Services\Stash\PreflightExecutor;
use App\Services\State\StateTransitionService;

final readonly class PreflightJobHandler implements JobHandler
{
    public function __construct(
        private PreflightExecutor $executor,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
        private StashdConfig $config,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::Preflight;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);
        $context->progress($job, 0, 1, 'Running preflight');

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        $result = $this->executor->execute($payload);
        $reviewUrl = rtrim($this->config->publicUrl, '/') . '/api/v1/stashes/preflight/' . (string) $command->id . '/review';
        $resultArray = $result->toResultArray($reviewUrl);

        $command->resultJson = json_encode($resultArray, JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        $job->progressCurrent = $result->estimatedItemCount;
        $job->progressTotal = max(1, $result->estimatedItemCount);
        $job->progressPercent = 100.0;
        $job->progressLabel = 'Preflight complete';
        $job->finishedAt = RecordTimestamps::now();
        $this->jobs->save($job);
        $context->progress($job, $job->progressCurrent, $job->progressTotal, $job->progressLabel);

        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->preflightCompleted($command, $job, $result->estimatedItemCount);
        $this->publisher->jobCompleted($job);
        $this->activity->commandCompleted($command);
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Preflight job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new \RuntimeException('Preflight command not found.');
    }
}
