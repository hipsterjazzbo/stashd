<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Ids\PrefixedId;

final readonly class JobId extends PrefixedId
{
    protected const string PREFIX = 'job';
}
