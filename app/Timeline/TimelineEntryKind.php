<?php

declare(strict_types=1);

namespace App\Timeline;

enum TimelineEntryKind: string
{
    case Chapter = 'chapter';
    case Segment = 'segment';
}
