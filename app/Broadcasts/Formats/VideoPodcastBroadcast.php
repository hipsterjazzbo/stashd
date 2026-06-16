<?php

declare(strict_types=1);

namespace App\Broadcasts\Formats;

use App\Broadcasts\BroadcastType;
use App\Broadcasts\Podcasts\PodcastAssetSelection;
use App\Broadcasts\Podcasts\PodcastBroadcastFormat;

final readonly class VideoPodcastBroadcast extends PodcastBroadcastFormat
{
    public function key(): string
    {
        return BroadcastType::VideoPodcast->value;
    }

    protected function selectAsset(string $mediaItemId): ?PodcastAssetSelection
    {
        return $this->videoAsset($mediaItemId);
    }

    protected function unavailableErrorCode(): string
    {
        return 'podcast_video_asset_unavailable';
    }
}
