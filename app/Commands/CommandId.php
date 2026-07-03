<?php

declare(strict_types=1);

namespace App\Commands;

use App\Support\Ids\PrefixedId;

final readonly class CommandId extends PrefixedId
{
    protected const string PREFIX = 'cmd';
}
