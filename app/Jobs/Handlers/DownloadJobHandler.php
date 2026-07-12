<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Config\YtdlpConfig;
use App\Downloads\DownloadException;
use App\Downloads\DownloadMediaItem;
use App\Downloads\DownloadProgressSmoother;
use App\Jobs\JobHandler;
use App\Jobs\JobHandlerContext;
use App\Jobs\JobIntent;
use App\Jobs\JobProgressUpdate;
use App\Jobs\JobRecord;
use App\Jobs\JobRepository;
use App\Jobs\JobState;
use App\Stashes\StashId;
use App\Support\PrefixedUlid;
use App\System\Activity\ActivityEventService;
use App\System\Event\EventPublisher;
use App\System\State\StateTransitionService;
use App\Vault\MediaItemId;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Duration;
use Tempest\DateTime\Timezone;
use Ytdlphp\DownloadProgress;

final readonly class DownloadJobHandler implements JobHandler
{
    public function __construct(
        private DownloadMediaItem $executor,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
        private YtdlpConfig $ytdlpConfig,
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
        $context->progress($job, JobProgressUpdate::ofPercent(0.0, 'Preparing download'));

        $payload = $job->payload ?? [];

        $mediaItemId = MediaItemId::parse((string) ($payload['media_item_id'] ?? ''));
        $stashId = StashId::parse((string) ($payload['stash_id'] ?? ''));
        $force = (bool) ($payload['force'] ?? false);

        try {
            // Downloads still run strictly one at a time (a single worker
            // tick claims and fully runs one job before the next tick
            // starts), so a queue of several items finishes sequentially,
            // not in parallel -- this only reports progress within whichever
            // download is currently running.
            $context->progress($job, JobProgressUpdate::ofPercent(0.0, 'Downloading via yt-dlp'));

            // Paces consecutive downloads (e.g. a large channel backfill) so
            // they don't hit YouTube back-to-back -- zero in testing via
            // YtdlpConfig defaults.
            $maxDelay = max($this->ytdlpConfig->minDelaySeconds, $this->ytdlpConfig->maxDelaySeconds);

            if ($maxDelay > 0) {
                sleep(random_int($this->ytdlpConfig->minDelaySeconds, $maxDelay));
            }

            $lastForwardedAt = microtime(true);
            $progressSmoother = new DownloadProgressSmoother();

            $result = $this->executor->execute(
                mediaItemId: $mediaItemId,
                stashId: $stashId,
                jobId: PrefixedUlid::parse((string) $job->id),
                force: $force,
                onProgress: function (DownloadProgress $progress) use ($job, $context, &$lastForwardedAt, $progressSmoother): void {
                    $isFinal = ($progress->percent ?? 0.0) >= 100.0;
                    $now = microtime(true);

                    if (! $isFinal && $now - $lastForwardedAt < 1.0) {
                        return;
                    }

                    $lastForwardedAt = $now;
                    $context->heartbeat($job);
                    $context->progress($job, $progressSmoother->update($progress));
                },
            );

            $command->result = $result->toArray();
            $this->commands->save($command);

            $job->progressPercent = 100.0;
            $job->progressLabel = $result->skipped ? 'Download skipped (already in Vault)' : 'Download complete';
            $job->progressEtaSeconds = Duration::zero();
            $job->finishedAt = DateTime::now(Timezone::UTC);
            $this->jobs->save($job);
            $context->progress($job, JobProgressUpdate::ofPercent(100.0, $job->progressLabel, 0));

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

        return $this->commands->find($job->commandId)
            ?? throw new \RuntimeException('Download command not found.');
    }
}
