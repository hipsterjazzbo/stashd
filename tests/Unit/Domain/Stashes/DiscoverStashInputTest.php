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
use App\Providers\YouTube\YouTubeYtdlpDiscoveryStrategy;
use App\Providers\YouTube\YouTubeYtdlpDownloadStrategy;
use App\Stashes\DiscoverStashInput;

function discoverStashInputWithFixtures(?string $apiKey, bool $realDownloads = false): DiscoverStashInput
{
    $fixturesDirectory = __DIR__ . '/../../../fixtures/providers/youtube/http';
    $map = json_decode((string) file_get_contents($fixturesDirectory . '/map.json'), true, flags: JSON_THROW_ON_ERROR);
    $http = new FixtureProviderHttpClient($fixturesDirectory, $map);
    $dataApiKey = new FixtureYouTubeDataApiKeyResolver($apiKey);
    $ytdlpConfig = new YtdlpConfig(
        binary: 'stub-yt-dlp',
        timeoutSeconds: 60,
        realDownloadsEnabledDefault: $realDownloads,
        videoFormatSelector: 'best',
        audioFormat: 'mp3',
        audioQualityKbps: 128,
    );
    $ytdlpGateway = new StubYtdlpGateway();
    $channelIds = new YouTubeChannelIdResolver($http);

    $youtubeProvider = new YouTubeProvider(
        dataApiKey: $dataApiKey,
        rssDiscovery: new YouTubeRssDiscoveryStrategy(
            http: $http,
            channelIds: $channelIds,
            parser: new YouTubeRssParser(),
            videos: new YouTubeVideoDiscovery($http),
        ),
        dataApiDiscovery: new YouTubeDataApiDiscoveryStrategy(dataApiKey: $dataApiKey, http: $http),
        dataApiMetadata: new YouTubeDataApiMetadataStrategy(dataApiKey: $dataApiKey, http: $http),
        downloadAdapter: new YouTubeYtdlpDownloadStrategy(config: $ytdlpConfig, gateway: $ytdlpGateway),
        ytdlpDiscovery: new YouTubeYtdlpDiscoveryStrategy(
            config: $ytdlpConfig,
            gateway: $ytdlpGateway,
            options: new \App\Downloads\Ytdlp\YtdlpOptionsBuilder($ytdlpConfig),
            channelIds: $channelIds,
        ),
        channelIds: $channelIds,
    );

    $registry = new ProviderRegistry(new FakeProvider(), $youtubeProvider);

    return new DiscoverStashInput($registry, new ProviderStrategySelector());
}

test('discover stash input prefers the data api strategy for the default preflight intent when keyed', function (): void {
    $executor = discoverStashInputWithFixtures(apiKey: 'test-api-key');

    $result = $executor->execute(['source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678']);

    expect($result->strategyKey)->toBe('youtube.data_api_discovery');
});

test('discover stash input falls back to the cheap rss strategy for the default preflight intent without a key', function (): void {
    $executor = discoverStashInputWithFixtures(apiKey: null);

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

test('discover stash input falls back to ytdlp discovery for the initial backfill intent without a key when ytdlp is enabled', function (): void {
    $executor = discoverStashInputWithFixtures(apiKey: null, realDownloads: true);

    $result = $executor->execute(
        ['source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678'],
        JobIntent::InitialBackfill,
    );

    expect($result->strategyKey)->toBe('youtube.ytdlp_discovery');
});
