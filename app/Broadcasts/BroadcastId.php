<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Support\Ids\PrefixedId;

final readonly class BroadcastId extends PrefixedId
{
    protected const string PREFIX = 'broadcast';
}
