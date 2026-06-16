<?php

declare(strict_types=1);

namespace App\Auth;

use Tempest\Container\Resettable;
use Tempest\Container\Singleton;

/** Request-scoped authenticated user resolved by middleware or tests. */
#[Singleton]
final class AuthContext implements Resettable
{
    private ?UserRecord $user = null;

    public function set(?UserRecord $user): void
    {
        $this->user = $user;
    }

    public function user(): ?UserRecord
    {
        return $this->user;
    }

    public function requireUser(): UserRecord
    {
        return $this->user ?? throw new AuthenticationRequired('Authentication required.');
    }

    public function reset(): void
    {
        $this->user = null;
    }
}
