<?php

declare(strict_types=1);

namespace App\System\Event;

use function Tempest\env;

/**
 * Single source of truth for the Mercure JWT secret. Both the hub
 * (MercureHubInitializer, validating incoming publish/subscribe requests via
 * the Caddyfile) and every JWT minter (AuthService's subscriber token) must
 * resolve the exact same fallback when the env var is unset, or subscriber
 * cookies would fail verification against the hub.
 */
final class MercureSecret
{
    private const string FALLBACK = 'unconfigured-mercure-secret';

    /** @return non-empty-string */
    public static function resolve(): string
    {
        $secret = env('MERCURE_JWT_SECRET');

        return is_string($secret) && $secret !== '' ? $secret : self::FALLBACK;
    }
}
