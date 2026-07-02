<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Support\Ids\PrefixedId;

final readonly class StashItemId extends PrefixedId
{
    protected const string PREFIX = 'item';
}
