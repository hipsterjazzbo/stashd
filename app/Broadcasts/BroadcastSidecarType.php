<?php

declare(strict_types=1);

namespace App\Broadcasts;

enum BroadcastSidecarType: string
{
    case TvShowNfo = 'tvshow_nfo';
    case EpisodeNfo = 'episode_nfo';
    case FeedXml = 'feed_xml';
    case Poster = 'poster';
    case Subtitle = 'subtitle';
}
