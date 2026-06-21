<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\Http\CurlProviderHttpClient;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;
use App\Providers\YouTube\SecretsBackedYouTubeDataApiKeyResolver;
use App\Providers\YouTube\YouTubeChannelIdResolver;
use App\Providers\YouTube\YouTubeDataApiDiscoveryStrategy;
use App\Providers\YouTube\YouTubeDataApiKeyResolver;
use App\System\Secret\SecretsService;
use App\System\Secret\SecretType;

/**
 * Opt-in live YouTube Data API discovery test — never run in normal CI.
 *
 * Requires STASHD_LIVE_PROVIDER_TESTS=1, YOUTUBE_DATA_API_KEY, and outbound network access to
 * googleapis.com/youtube.com. Verifies the fixture-driven unit tests against a real channel's
 * full-enumeration discovery, per the T5 acceptance bar: with a key, discovery returns more than
 * fifteen items, each tagged with a video type.
 *
 * The HTTP client is constructed explicitly (CurlProviderHttpClient), not resolved from the
 * container, because ProviderHttpClientInitializer always binds the fixture client while
 * ENVIRONMENT=testing. The API key resolver IS resolved from the container, after seeding it via
 * SecretsService, to prove the real SecretsService -> resolver path (not just the env fallback).
 */
test('live youtube data api discovery strategy returns more than fifteen items with video types', function (): void {
    if (! filter_var(getenv('STASHD_LIVE_PROVIDER_TESTS') ?: '0', FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set STASHD_LIVE_PROVIDER_TESTS=1 to run live provider tests.');
    }

    $apiKey = getenv('YOUTUBE_DATA_API_KEY') ?: null;

    if ($apiKey === null || trim($apiKey) === '') {
        $this->markTestSkipped('Set YOUTUBE_DATA_API_KEY to run this live test.');
    }

    $handle = getenv('STASHD_LIVE_PROVIDER_HANDLE') ?: 'mkbhd';

    $secrets = $this->container->get(SecretsService::class);
    $secrets->put(SecretsBackedYouTubeDataApiKeyResolver::SECRET_KEY, SecretType::ApiKey, $apiKey);

    $http = new CurlProviderHttpClient();
    $dataApiKey = $this->container->get(YouTubeDataApiKeyResolver::class);

    expect($dataApiKey->hasKey())->toBeTrue();

    $channel = (new YouTubeChannelIdResolver($http))->resolve('handle:' . $handle);

    $strategy = new YouTubeDataApiDiscoveryStrategy(dataApiKey: $dataApiKey, http: $http);

    $items = $strategy->discover(new ResolvedInput(
        providerKey: 'youtube',
        inputType: 'channel',
        sourceUri: StashdUri::parse('https://www.youtube.com/channel/' . $channel->id),
        providerInputId: $channel->id,
    ));

    $contentTypeCounts = [];
    foreach ($items as $item) {
        $contentTypeCounts[$item->contentType ?? 'null'] = ($contentTypeCounts[$item->contentType ?? 'null'] ?? 0) + 1;
    }

    fwrite(STDERR, sprintf(
        "[live-data-api-discovery] handle=%s channelId=%s itemCount=%d contentTypes=%s\n",
        $handle,
        $channel->id,
        count($items),
        json_encode($contentTypeCounts),
    ));

    expect(count($items))->toBeGreaterThan(15);

    foreach ($items as $item) {
        expect($item->contentType)->not->toBeNull();
    }

    expect(count($contentTypeCounts))->toBeGreaterThanOrEqual(2);
})->group('live');
