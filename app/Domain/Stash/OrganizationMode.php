<?php

declare(strict_types=1);

namespace App\Domain\Stash;

enum OrganizationMode: string
{
    case Flat = 'flat';
    case Chronological = 'chronological';
    case Series = 'series';
    case SeasonedSeries = 'seasoned_series';
}
