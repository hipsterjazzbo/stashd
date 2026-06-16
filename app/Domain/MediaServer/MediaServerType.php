<?php

declare(strict_types=1);

namespace App\Domain\MediaServer;

enum MediaServerType: string
{
    case Jellyfin = 'jellyfin';
    case Plex = 'plex';
}
