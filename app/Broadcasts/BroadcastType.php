<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Stashes\DownloadPolicy;

enum BroadcastType: string
{
    case FilesystemSeries = 'filesystem_series';
    case JellyfinSeries = 'jellyfin_series';
    case PlexSeries = 'plex_series';
    case AudioPodcast = 'audio_podcast';
    case VideoPodcast = 'video_podcast';

    /**
     * Whether a stash on `$policy` can actually feed this broadcast type.
     *
     * `metadata_only` never downloads anything, so it satisfies nothing.
     * `audio_only` never downloads a video-kind asset, so it can't feed
     * `video_podcast` specifically — `PodcastAssetSelector::videoAsset()`
     * requires `AssetKind::Video`, with no audio-to-video derivation. The
     * filesystem/Jellyfin/Plex series types hardlink whatever Vault original
     * exists regardless of kind, so they aren't restricted here.
     */
    public function isSatisfiedByDownloadPolicy(DownloadPolicy $policy): bool
    {
        return match ($policy) {
            DownloadPolicy::MetadataOnly => false,
            DownloadPolicy::AudioOnly => $this !== self::VideoPodcast,
            DownloadPolicy::Video, DownloadPolicy::ManualDownload => true,
        };
    }

    /** Whether this type generates a media-server library layout (seasons/episodes), as opposed to a podcast feed. */
    public function isSeries(): bool
    {
        return match ($this) {
            self::FilesystemSeries, self::JellyfinSeries, self::PlexSeries => true,
            self::AudioPodcast, self::VideoPodcast => false,
        };
    }
}
