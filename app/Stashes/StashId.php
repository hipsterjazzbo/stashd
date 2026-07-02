<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Support\Ids\PrefixedId;

final readonly class StashId extends PrefixedId
{
    protected const string PREFIX = 'stash';
}
