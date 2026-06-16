<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\System\Secret\SecretsService;
use App\System\Secret\SecretType;

test('secrets service encrypts and decrypts roundtrip', function (): void {
    $secrets = $this->container->get(SecretsService::class);

    $secrets->put('youtube.api_key', SecretType::ApiKey, 'super-secret-value', ['provider' => 'youtube']);

    expect($secrets->get('youtube.api_key'))->toBe('super-secret-value');
});

test('secrets service updates existing keys', function (): void {
    $secrets = $this->container->get(SecretsService::class);

    $secrets->put('plex.token', SecretType::BroadcastToken, 'first');
    $secrets->put('plex.token', SecretType::BroadcastToken, 'second');

    expect($secrets->get('plex.token'))->toBe('second');
});

test('secrets service revoke prevents lookup', function (): void {
    $secrets = $this->container->get(SecretsService::class);

    $secrets->put('oauth.refresh', SecretType::OauthToken, 'token-value');
    $secrets->revoke('oauth.refresh');

    expect($secrets->get('oauth.refresh'))->toBeNull();
});

test('secrets service redacts long tokens in strings', function (): void {
    $secrets = $this->container->get(SecretsService::class);

    $redacted = $secrets->redact('Authorization: Bearer st_abcdefghijklmnopqrstuvwxyz123456');

    expect($redacted)->toContain('[REDACTED]')
        ->and($redacted)->not->toContain('st_abcdefghijklmnopqrstuvwxyz123456');
});
