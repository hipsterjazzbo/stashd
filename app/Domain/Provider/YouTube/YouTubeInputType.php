<?php

declare(strict_types=1);

namespace App\Domain\Provider\YouTube;

enum YouTubeInputType: string
{
    case Channel = 'channel';
    case Playlist = 'playlist';
    case Video = 'video';
}
