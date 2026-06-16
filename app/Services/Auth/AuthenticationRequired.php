<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

final class AuthenticationRequired extends RuntimeException
{
}
