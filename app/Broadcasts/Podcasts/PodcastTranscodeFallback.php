<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Commands\CommandDispatchService;
use App\Commands\CommandType;
use App\Vault\AssetId;
use App\Vault\AssetKind;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemId;

/**
 * Decides whether a missing podcast episode asset can be generated instead of
 * just failing. Single place that knows which kinds have a working transcode pathway.
 */
final readonly class PodcastTranscodeFallback
{
    public function __construct(
        private AssetRepository $assets,
        private PodcastAssetSelector $assetSelector,
        private CommandDispatchService $dispatch,
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

        if ($existingAudio !== null && in_array($existingAudio->state, [AssetState::Pending, AssetState::Processing], true)) {
            return 'podcast_audio_transcode_pending';
        }

        if ($existingAudio !== null && $existingAudio->state === AssetState::Failed) {
            return 'podcast_audio_transcode_failed';
        }

        $videoOriginal = $this->assetSelector->videoOriginalForAudioFallback($mediaItemId);

        if ($videoOriginal === null) {
            return null;
        }

        $audioAsset = $this->assets->create(
            mediaItemId: $mediaItemId,
            role: AssetRole::PodcastAudio,
            kind: AssetKind::Audio,
            state: AssetState::Pending,
        );
        $audioAsset->derivedFromAssetId = AssetId::parse((string) $videoOriginal->id);
        $this->assets->save($audioAsset);

        $this->dispatch->dispatch(CommandType::AssetTranscodePodcastAudio, [
            'media_item_id' => $mediaItemId->toString(),
            'source_asset_id' => (string) $videoOriginal->id,
            'asset_id' => (string) $audioAsset->id,
        ]);

        return 'podcast_audio_transcode_pending';
    }
}
