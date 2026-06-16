<?php

declare(strict_types=1);

namespace App\Stashes;

enum StashInputType: string
{
    case Channel = 'channel';
    case Playlist = 'playlist';
    case UrlList = 'url_list';
    case Video = 'video';
}
