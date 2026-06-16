<?php

declare(strict_types=1);

namespace App\Providers;

enum StrategyCost: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case LastResort = 'last_resort';
}
