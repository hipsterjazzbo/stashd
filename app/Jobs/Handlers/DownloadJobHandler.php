<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Downloads\DownloadException;
use App\Downloads\DownloadMediaItem;
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

final readonly class DownloadJobHandler implements JobHandler
{
    public function __construct(
        private DownloadMediaItem $executor,
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
            $job->finishedAt = DateTime::now(Timezone::UTC);
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
