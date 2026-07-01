<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Broadcasts\BroadcastItemRepository;
use App\Broadcasts\BroadcastRepository;
use App\Broadcasts\Podcasts\PodcastMediaKind;
use App\Commands\CommandDispatchService;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Commands\CommandType;
use App\Jobs\JobHandler;
use App\Jobs\JobHandlerContext;
use App\Jobs\JobIntent;
use App\Jobs\JobProgressUpdate;
use App\Jobs\JobRecord;
use App\Jobs\JobRepository;
use App\Jobs\JobState;
use App\Support\PrefixedUlid;
use App\System\Activity\ActivityEventService;
use App\System\Event\EventPublisher;
use App\System\State\StateTransitionService;
use App\Transcoding\Ffmpeg\FfmpegProgress;
use App\Transcoding\TranscodePodcastAudioAsset;
use RuntimeException;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final readonly class TranscodePodcastAudioJobHandler implements JobHandler
{
    public function __construct(
        private TranscodePodcastAudioAsset $executor,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private BroadcastItemRepository $broadcastItems,
        private BroadcastRepository $broadcasts,
        private CommandDispatchService $dispatch,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::TranscodePodcastAudio;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);
        $context->progress($job, JobProgressUpdate::ofPercent(0.0, 'Preparing transcode'));

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        $mediaItemId = PrefixedUlid::parse((string) ($payload['media_item_id'] ?? ''));
        $sourceAssetId = PrefixedUlid::parse((string) ($payload['source_asset_id'] ?? ''));
        $audioAssetId = PrefixedUlid::parse((string) ($payload['asset_id'] ?? ''));

        $lastForwardedAt = microtime(true);

        $result = $this->executor->execute(
            mediaItemId: $mediaItemId,
            sourceAssetId: $sourceAssetId,
            audioAssetId: $audioAssetId,
            jobId: PrefixedUlid::parse((string) $job->id),
            onProgress: function (FfmpegProgress $progress) use ($job, $context, &$lastForwardedAt): void {
                // ffmpeg's -progress pipe emits roughly every 0.5s; forwarding
                // every update would be a DB write + SSE publish each time for
                // no benefit. The final update always forwards regardless.
                $now = microtime(true);
                $isFinal = $progress->percent >= 100.0;

                if (! $isFinal && $now - $lastForwardedAt < 1.0) {
                    return;
                }

                $lastForwardedAt = $now;
                $context->heartbeat($job);
                $context->progress($job, JobProgressUpdate::ofPercent(
                    percent: $progress->percent,
                    label: sprintf('Transcoding: %d%%', (int) $progress->percent),
                    etaSeconds: $progress->etaSeconds,
                ));
            },
        );

        $command->resultJson = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        $job->progressPercent = 100.0;
        $job->progressLabel = 'Podcast audio ready';
        $job->progressEtaSeconds = 0;
        $job->finishedAt = DateTime::now(Timezone::UTC);
        $this->jobs->save($job);
        $context->progress($job, JobProgressUpdate::ofPercent(100.0, $job->progressLabel, 0));

        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->podcastAudioTranscodeCompleted($command, $job, $result);
        $this->publisher->jobCompleted($job);
        $this->activity->commandCompleted($command);

        $this->retriggerAffectedBroadcasts((string) $mediaItemId);
    }

    /**
     * Re-runs broadcast.rebuild for every audio-configured podcast broadcast
     * referencing this media item, now that its generated audio asset is
     * Ready -- closes the loop without requiring a manual rebuild. Rebuild is
     * idempotent/safe per the broadcast rules, so a redundant dispatch (e.g.
     * several media items in one broadcast finishing around the same time)
     * is wasteful but not incorrect.
     */
    private function retriggerAffectedBroadcasts(string $mediaItemId): void
    {
        $broadcastIds = [];

        foreach ($this->broadcastItems->listForMediaItem(PrefixedUlid::parse($mediaItemId)) as $item) {
            $broadcast = $this->broadcasts->find(PrefixedUlid::parse($item->broadcastId));

            if (
                $broadcast !== null
                && $broadcast->type === 'podcast'
                && PodcastMediaKind::forBroadcast($broadcast) === PodcastMediaKind::Audio
            ) {
                $broadcastIds[(string) $broadcast->id] = true;
            }
        }

        foreach (array_keys($broadcastIds) as $broadcastId) {
            $this->dispatch->dispatch(CommandType::BroadcastRebuild, ['broadcast_id' => $broadcastId]);
        }
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new RuntimeException('Transcode job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new RuntimeException('Transcode command not found.');
    }
}
