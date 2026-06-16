<?php

declare(strict_types=1);

namespace App\Providers;

enum StrategyPurpose: string
{
    case Discovery = 'discovery';
    case Metadata = 'metadata';
    case Download = 'download';
    case Availability = 'availability';
}
