<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Commands\CommandDispatchService;
use App\Commands\CommandType;
use App\System\State\StateTransitionService;
use App\Vault\AssetId;
use App\Vault\AssetKind;
use App\Vault\AssetRecord;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemId;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

/**
 * Decides whether a missing podcast episode asset can be generated instead of
 * just failing. Single place that knows which kinds have a working transcode pathway.
 */
final readonly class PodcastTranscodeFallback
{
    /**
     * A definitively failed transcode is retried after this long, not
     * immediately -- without it, TranscodePodcastAudioJobHandler's own
     * retrigger-on-failure (which runs right after every failed attempt)
     * would spin a broken transcode (e.g. ffmpeg misconfigured, corrupt
     * source) in a tight loop.
     */
    private const int RETRY_COOLDOWN_SECONDS = 300;

    /**
     * A transcode "in progress" longer than this didn't fail -- it just
     * never got a chance to. This mirrors JobWorkerService::HARD_STALL_SECONDS:
     * a job that stalls hard enough for long enough is recovered at the
     * queue level, but that recovery never touches the asset row it was
     * transcoding into, so without this the asset (and every broadcast
     * referencing it) stays stuck reporting "pending" forever even after
     * the job itself is retried and fails or succeeds under a new attempt.
     */
    private const int STUCK_SECONDS = 1800;

    public function __construct(
        private AssetRepository $assets,
        private PodcastAssetSelector $assetSelector,
        private CommandDispatchService $dispatch,
        private StateTransitionService $transitions,
    ) {
    }

    /** Returns a BroadcastItemRecord.lastError code, or null if no fallback applies. */
    public function triggerIfNeeded(MediaItemId $mediaItemId, PodcastMediaKind $kind): ?string
    {
        return match ($kind) {
            PodcastMediaKind::Audio => $this->triggerAudioTranscode($mediaItemId),
            // No video transcode pathway exists yet (out of scope for this
            // slice, a v1 non-goal). If that ever changes, it's a new
            // private method here with the same shape as
            // triggerAudioTranscode() below — not a second mechanism
            // elsewhere.
            PodcastMediaKind::Video => null,
        };
    }

    private function triggerAudioTranscode(MediaItemId $mediaItemId): ?string
    {
        $existingAudio = $this->assets->findByMediaItemAndRole($mediaItemId, AssetRole::PodcastAudio);

        // Ready/Stale/Missing shouldn't reach here (selectAsset() would have
        // used a Ready asset; Stale/Missing aren't reachable via a direct
        // transition to Failed, so they're deliberately left alone rather
        // than risking an InvalidStateTransition -- same as this method did
        // before retries existed, just falls through to creating a fresh row).
        $retryable = $existingAudio !== null && in_array($existingAudio->state, [AssetState::Pending, AssetState::Processing, AssetState::Failed], true);

        if ($retryable && ! $this->readyToRetry($existingAudio)) {
            return $existingAudio->state === AssetState::Failed
                ? 'podcast_audio_transcode_failed'
                : 'podcast_audio_transcode_pending';
        }

        $videoOriginal = $this->assetSelector->videoOriginalForAudioFallback($mediaItemId);

        if ($videoOriginal === null) {
            return $retryable ? 'podcast_audio_transcode_failed' : null;
        }

        if ($retryable) {
            $audioAsset = $existingAudio;
            // Processing/Pending -> Pending isn't a valid direct transition
            // (AssetState::allowedTransitions), so a stuck attempt is
            // declared dead first -- Failed -> Pending is what actually
            // requeues it below.
            if ($audioAsset->state !== AssetState::Failed) {
                $this->transitions->transitionAsset($audioAsset, AssetState::Failed);
            }
            $this->transitions->transitionAsset($audioAsset, AssetState::Pending);
        } else {
            $audioAsset = $this->assets->create(
                mediaItemId: $mediaItemId,
                role: AssetRole::PodcastAudio,
                kind: AssetKind::Audio,
                state: AssetState::Pending,
            );
            $audioAsset->derivedFromAssetId = AssetId::fromPrimaryKey($videoOriginal->id);
            $this->assets->save($audioAsset);
        }

        $this->dispatch->dispatch(CommandType::AssetTranscodePodcastAudio, [
            'media_item_id' => $mediaItemId->toString(),
            'source_asset_id' => (string) $videoOriginal->id,
            'asset_id' => (string) $audioAsset->id,
        ]);

        return 'podcast_audio_transcode_pending';
    }

    private function readyToRetry(AssetRecord $asset): bool
    {
        if ($asset->updatedAt === null) {
            return true;
        }

        $seconds = $asset->state === AssetState::Failed ? self::RETRY_COOLDOWN_SECONDS : self::STUCK_SECONDS;

        return $asset->updatedAt->isBefore(DateTime::now(Timezone::UTC)->minusSeconds($seconds));
    }
}
