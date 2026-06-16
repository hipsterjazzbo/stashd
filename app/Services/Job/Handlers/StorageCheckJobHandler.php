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
use App\Services\Health\HealthService;
use App\Services\Job\JobHandler;
use App\Services\Job\JobHandlerContext;
use App\Services\State\StateTransitionService;
use App\Services\Storage\StorageCapabilityChecker;

final readonly class StorageCheckJobHandler implements JobHandler
{
    public function __construct(
        private StorageCapabilityChecker $storageChecks,
        private HealthService $health,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::StorageCheck;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->optionalCommand($job);
        if ($command !== null) {
            $this->transitions->transitionCommand($command, CommandState::Running);
        }

        $context->heartbeat($job);
        $context->progress($job, 0, 2, 'Checking storage roots');

        $this->storageChecks->checkAll();
        $context->heartbeat($job);
        $context->progress($job, 1, 2, 'Evaluating health report');

        $report = $this->health->report();
        $ok = $report->status === 'ok';

        $result = [
            'status' => $report->status,
            'storage' => $report->toDetailedArray()['storage'] ?? [],
        ];

        if ($command !== null) {
            $command->resultJson = json_encode($result, JSON_THROW_ON_ERROR);
            $this->commands->save($command);
        }

        $job->progressCurrent = 2;
        $job->progressTotal = 2;
        $job->progressPercent = 100.0;
        $job->progressLabel = 'Storage check complete';
        $job->finishedAt = RecordTimestamps::now();
        $this->jobs->save($job);

        $this->transitions->transitionJob($job, JobState::Ready);
        if ($command !== null) {
            $this->transitions->transitionCommand($command, CommandState::Completed);
            $this->activity->commandCompleted($command);
        }

        $this->activity->storageCheckCompleted($job, $ok);
        $this->publisher->jobCompleted($job);
    }

    private function optionalCommand(JobRecord $job): ?CommandRecord
    {
        if ($job->commandId === null) {
            return null;
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId));
    }
}
