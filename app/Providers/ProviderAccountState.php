<?php

declare(strict_types=1);

namespace App\Providers;

enum ProviderAccountState: string
{
    case Ready = 'ready';
    case Failed = 'failed';
    case Disabled = 'disabled';
}
