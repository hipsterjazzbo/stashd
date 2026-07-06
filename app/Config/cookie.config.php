<?php

declare(strict_types=1);

use Tempest\Http\Cookie\CookieConfig;

// mercureAuthorization must reach Caddy's Mercure hub as the raw subscriber
// JWT (EventSubscriptionController) -- Caddy verifies it itself using
// MercureSecret, with no knowledge of Tempest's own cookie encryption.
// Tempest's default SetCookieHeadersMiddleware encrypts every cookie value
// unless its key is explicitly whitelisted here, which silently turned the
// JWT into an opaque encrypted blob Caddy couldn't parse, rejecting every
// subscription with 401.
return new CookieConfig(
    plaintextCookies: ['mercureAuthorization'],
);
