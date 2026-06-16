<?php

declare(strict_types=1);

namespace App\Auth;

enum UserRole: string
{
    case Owner = 'owner';
}
