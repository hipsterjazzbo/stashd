<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

enum BroadcastTriggerType: string
{
    case JellyfinScan = 'jellyfin_scan';
    case PlexScan = 'plex_scan';
    case Webhook = 'webhook';
}
