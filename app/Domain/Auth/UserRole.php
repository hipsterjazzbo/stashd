<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum UserRole: string
{
    case Owner = 'owner';
}
