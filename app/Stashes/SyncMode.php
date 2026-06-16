<?php

declare(strict_types=1);

namespace App\Stashes;

enum SyncMode: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
