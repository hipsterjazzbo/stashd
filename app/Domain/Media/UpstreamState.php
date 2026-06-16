<?php

declare(strict_types=1);

namespace App\Domain\Media;

enum UpstreamState: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Private = 'private';
    case Deleted = 'deleted';
    case RegionBlocked = 'region_blocked';
    case Unknown = 'unknown';
}
