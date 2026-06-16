<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

enum BroadcastSidecarKind: string
{
    case TvShowNfo = 'tvshow_nfo';
    case EpisodeNfo = 'episode_nfo';
    case Poster = 'poster';
}
