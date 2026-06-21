<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stashes;

use App\Config\YtdlpConfig;
use App\Downloads\Ytdlp\StubYtdlpGateway;
use App\Jobs\JobIntent;
use App\Providers\Fake\FakeProvider;
use App\Providers\Http\FixtureProviderHttpClient;
use App\Providers\ProviderRegistry;
use App\Providers\ProviderStrategySelector;
use App\Providers\YouTube\FixtureYouTubeDataApiKeyResolver;
use App\Providers\YouTube\YouTubeChannelIdResolver;
use App\Providers\YouTube\YouTubeDataApiDiscoveryStrategy;
use App\Providers\YouTube\YouTubeDataApiMetadataStrategy;
use App\Providers\YouTube\YouTubeProvider;
use App\Providers\YouTube\YouTubeRssDiscoveryStrategy;
use App\Providers\YouTube\YouTubeRssParser;
use App\Providers\YouTube\YouTubeVideoDiscovery;
use App\Providers\YouTube\YouTubeYtdlpDownloadStrategy;
use App\Stashes\DiscoverStashInput;

function discoverStashInputWithFixtures(?string $apiKey): DiscoverStashInput
{
    $fixturesDirectory = __DIR__ . '/../../../fixtures/providers/youtube/http';
    $map = json_decode((string) file_get_contents($fixturesDirectory . '/map.json'), true, flags: JSON_THROW_ON_ERROR);
    $http = new FixtureProviderHttpClient($fixturesDirectory, $map);
    $dataApiKey = new FixtureYouTubeDataApiKeyResolver($apiKey);

    $youtubeProvider = new YouTubeProvider(
        dataApiKey: $dataApiKey,
        rssDiscovery: new YouTubeRssDiscoveryStrategy(
            http: $http,
            channelIds: new YouTubeChannelIdResolver($http),
            parser: new YouTubeRssParser(),
            videos: new YouTubeVideoDiscovery($http),
        ),
        dataApiDiscovery: new YouTubeDataApiDiscoveryStrategy(dataApiKey: $dataApiKey, http: $http),
        dataApiMetadata: new YouTubeDataApiMetadataStrategy(dataApiKey: $dataApiKey, http: $http),
        downloadAdapter: new YouTubeYtdlpDownloadStrategy(
            config: new YtdlpConfig(
                binary: 'stub-yt-dlp',
                timeoutSeconds: 60,
                realDownloadsEnabledDefault: false,
                videoFormatSelector: 'best',
                audioFormat: 'mp3',
                audioQualityKbps: 128,
            ),
            gateway: new StubYtdlpGateway(),
        ),
        channelIds: new YouTubeChannelIdResolver($http),
    );

    $registry = new ProviderRegistry(new FakeProvider(), $youtubeProvider);

    return new DiscoverStashInput($registry, new ProviderStrategySelector());
}

test('discover stash input uses the cheap rss strategy for the default preflight intent', function (): void {
    $executor = discoverStashInputWithFixtures(apiKey: 'test-api-key');

    $result = $executor->execute(['source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678']);

    expect($result->strategyKey)->toBe('youtube.rss');
});

test('discover stash input uses the data api strategy for the initial backfill intent when keyed', function (): void {
    $executor = discoverStashInputWithFixtures(apiKey: 'test-api-key');

    $result = $executor->execute(
        ['source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678'],
        JobIntent::InitialBackfill,
    );

    expect($result->strategyKey)->toBe('youtube.data_api_discovery')
        ->and($result->estimatedItemCount)->toBeGreaterThan(15);
});

test('discover stash input falls back to rss for the initial backfill intent without a key', function (): void {
    $executor = discoverStashInputWithFixtures(apiKey: null);

    $result = $executor->execute(
        ['source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678'],
        JobIntent::InitialBackfill,
    );

    expect($result->strategyKey)->toBe('youtube.rss');
});
