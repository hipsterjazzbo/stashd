<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\Ids\PrefixedId;

final readonly class UserId extends PrefixedId
{
    protected const string PREFIX = 'user';
}
