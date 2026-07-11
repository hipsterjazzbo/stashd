<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use RuntimeException;
use SensitiveParameter;

use function Tempest\env;

/**
 * Deterministic, non-reversible lookup digest for encrypted podcast tokens.
 *
 * SIGNING_KEY is generated and persisted with the application's encrypted
 * secrets. The fixed prefix keeps this HMAC use separate from other uses of
 * that key material.
 */
final class PodcastTokenDigest
{
    public function for(#[SensitiveParameter] string $token): string
    {
        $key = env('SIGNING_KEY');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Podcast token lookup requires a configured signing key.');
        }

        return hash_hmac('sha256', "stashd:podcast-token:v1\0" . $token, $key);
    }
}
