<?php

declare(strict_types=1);

namespace App\System\Activity;

enum ActivityLevel: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
