<?php

declare(strict_types=1);

namespace App\Timeline;

enum TimelineEntryState: string
{
    case Ready = 'ready';
    case Stale = 'stale';
    case Failed = 'failed';
}
