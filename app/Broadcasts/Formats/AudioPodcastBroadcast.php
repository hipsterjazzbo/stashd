<?php

declare(strict_types=1);

namespace App\Broadcasts\Formats;

use App\Broadcasts\BroadcastType;
use App\Broadcasts\Podcasts\PodcastAssetSelection;
use App\Broadcasts\Podcasts\PodcastBroadcastFormat;

final readonly class AudioPodcastBroadcast extends PodcastBroadcastFormat
{
    public function key(): string
    {
        return BroadcastType::AudioPodcast->value;
    }

    protected function selectAsset(string $mediaItemId): ?PodcastAssetSelection
    {
        return $this->audioAsset($mediaItemId);
    }

    protected function unavailableErrorCode(): string
    {
        return 'podcast_audio_asset_unavailable';
    }
}
