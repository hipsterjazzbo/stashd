<?php

declare(strict_types=1);

namespace App\Domain\Stash;

enum StashInputType: string
{
    case Channel = 'channel';
    case Playlist = 'playlist';
    case UrlList = 'url_list';
    case Video = 'video';
}
