<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Config\YouTubeConfig;
use App\Providers\YouTube\SecretsBackedYouTubeDataApiKeyResolver;
use App\Providers\YouTube\YouTubeDataApiKeyResolver;
use App\System\Secret\SecretsService;
use App\System\Secret\SecretType;

test('youtube data api key resolver prefers the stored secret over the env fallback', function (): void {
    $this->container->singleton(YouTubeConfig::class, new YouTubeConfig(dataApiKey: 'env-fallback-key'));

    $secrets = $this->container->get(SecretsService::class);
    $secrets->put(SecretsBackedYouTubeDataApiKeyResolver::SECRET_KEY, SecretType::ApiKey, 'secret-key');

    $resolver = $this->container->get(YouTubeDataApiKeyResolver::class);

    expect($resolver->key())->toBe('secret-key')
        ->and($resolver->hasKey())->toBeTrue();
});

test('youtube data api key resolver falls back to the env key when no secret is stored', function (): void {
    $this->container->singleton(YouTubeConfig::class, new YouTubeConfig(dataApiKey: 'env-fallback-key'));

    $resolver = $this->container->get(YouTubeDataApiKeyResolver::class);

    expect($resolver->key())->toBe('env-fallback-key')
        ->and($resolver->hasKey())->toBeTrue();
});

test('youtube data api key resolver reports no key when neither secret nor env is set', function (): void {
    $this->container->singleton(YouTubeConfig::class, new YouTubeConfig(dataApiKey: null));

    $resolver = $this->container->get(YouTubeDataApiKeyResolver::class);

    expect($resolver->key())->toBeNull()
        ->and($resolver->hasKey())->toBeFalse();
});

test('youtube data api key resolver picks up a newly stored secret without restarting anything', function (): void {
    $this->container->singleton(YouTubeConfig::class, new YouTubeConfig(dataApiKey: null));

    $resolver = $this->container->get(YouTubeDataApiKeyResolver::class);

    expect($resolver->hasKey())->toBeFalse();

    $secrets = $this->container->get(SecretsService::class);
    $secrets->put(SecretsBackedYouTubeDataApiKeyResolver::SECRET_KEY, SecretType::ApiKey, 'fresh-key');

    expect($resolver->key())->toBe('fresh-key');
});
