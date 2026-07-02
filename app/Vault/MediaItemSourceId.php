<?php

declare(strict_types=1);

namespace App\Vault;

use App\Support\Ids\PrefixedId;

final readonly class MediaItemSourceId extends PrefixedId
{
    protected const string PREFIX = 'source';
}
