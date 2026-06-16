<?php

declare(strict_types=1);

namespace App\MediaServers;

enum MediaServerType: string
{
    case Jellyfin = 'jellyfin';
    case Plex = 'plex';
}
