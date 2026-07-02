<?php

declare(strict_types=1);

namespace App\Vault;

use App\Support\Ids\PrefixedId;

final readonly class MediaItemId extends PrefixedId
{
    protected const string PREFIX = 'media';
}
