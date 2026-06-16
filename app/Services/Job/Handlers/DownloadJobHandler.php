<?php

declare(strict_types=1);

namespace App\Services\Job\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandState;
use App\Domain\Download\DownloadException;
use App\Domain\Job\JobIntent;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Services\Activity\ActivityEventService;
use App\Services\Download\DownloadExecutor;
use App\Services\Event\EventPublisher;
use App\Services\Job\JobHandler;
use App\Services\Job\JobHandlerContext;
use App\Services\State\StateTransitionService;

final readonly class DownloadJobHandler implements JobHandler
{
    public function __construct(
        private DownloadExecutor $executor,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::Download;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);
        $context->progress($job, 0, 4, 'Preparing download');

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        $mediaItemId = PrefixedUlid::parse((string) ($payload['media_item_id'] ?? ''));
        $stashId = PrefixedUlid::parse((string) ($payload['stash_id'] ?? ''));
        $force = (bool) ($payload['force'] ?? false);

        try {
            $context->progress($job, 1, 4, 'Downloading to temp');
            $result = $this->executor->execute($mediaItemId, $stashId, PrefixedUlid::parse((string) $job->id), $force);
            $context->progress($job, 3, 4, 'Vault ingest complete');

            $command->resultJson = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
            $this->commands->save($command);

            $job->progressCurrent = 4;
            $job->progressTotal = 4;
            $job->progressPercent = 100.0;
            $job->progressLabel = $result->skipped ? 'Download skipped (already in Vault)' : 'Download complete';
            $job->finishedAt = RecordTimestamps::now();
            $this->jobs->save($job);
            $context->progress($job, 4, 4, $job->progressLabel);

            $this->transitions->transitionJob($job, JobState::Ready);
            $this->transitions->transitionCommand($command, CommandState::Completed);
            $this->activity->downloadCompleted($command, $job, $result);
            $this->publisher->jobCompleted($job);
            $this->activity->commandCompleted($command);
        } catch (DownloadException $exception) {
            throw $exception;
        }
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Download job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new \RuntimeException('Download command not found.');
    }
}
