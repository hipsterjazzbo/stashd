<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Support\Ids\PrefixedId;

final readonly class TimelineEntryId extends PrefixedId
{
    protected const string PREFIX = 'timeline';
}
