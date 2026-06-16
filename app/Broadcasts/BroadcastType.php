<?php

declare(strict_types=1);

namespace App\Broadcasts;

enum BroadcastType: string
{
    case FilesystemSeries = 'filesystem_series';
    case JellyfinSeries = 'jellyfin_series';
    case PlexSeries = 'plex_series';
    case AudioPodcast = 'audio_podcast';
    case VideoPodcast = 'video_podcast';
}
