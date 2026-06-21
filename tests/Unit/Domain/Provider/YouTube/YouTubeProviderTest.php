<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider\YouTube;

use App\Config\YtdlpConfig;
use App\Providers\Http\FixtureProviderHttpClient;
use App\Providers\ProviderStrategySelector;
use App\Providers\StrategyCost;
use App\Providers\StrategyPurpose;
use App\Providers\StrategySelectionOptions;
use App\Providers\YouTube\FixtureYouTubeDataApiKeyResolver;
use App\Providers\YouTube\YouTubeChannelIdResolver;
use App\Providers\YouTube\YouTubeDataApiDiscoveryStrategy;
use App\Providers\YouTube\YouTubeDataApiMetadataStrategy;
use App\Providers\YouTube\YouTubeProvider;
use App\Providers\YouTube\YouTubeRssDiscoveryStrategy;
use App\Providers\YouTube\YouTubeRssParser;
use App\Providers\YouTube\YouTubeVideoDiscovery;
use App\Providers\YouTube\YouTubeYtdlpDownloadStrategy;

function youtubeProviderWithFixtures(?string $apiKey = null, bool $realDownloads = false): YouTubeProvider
{
    $fixturesDirectory = __DIR__ . '/../../../../fixtures/providers/youtube/http';
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

    return new YouTubeProvider(
        dataApiKey: $dataApiKey,
        rssDiscovery: new YouTubeRssDiscoveryStrategy(
            http: $http,
            channelIds: new YouTubeChannelIdResolver($http),
            parser: new YouTubeRssParser(),
            videos: new YouTubeVideoDiscovery($http),
        ),
        dataApiDiscovery: new YouTubeDataApiDiscoveryStrategy(
            dataApiKey: $dataApiKey,
            http: $http,
        ),
        dataApiMetadata: new YouTubeDataApiMetadataStrategy(
            dataApiKey: $dataApiKey,
            http: $http,
        ),
        downloadAdapter: new YouTubeYtdlpDownloadStrategy(
            config: $ytdlpConfig,
            gateway: new \App\Downloads\Ytdlp\StubYtdlpGateway(),
        ),
        channelIds: new YouTubeChannelIdResolver($http),
    );
}

test('youtube provider fixture channel discovery matches committed expectations', function (): void {
    $fixture = json_decode(
        file_get_contents(__DIR__ . '/../../../../fixtures/providers/youtube/channel_demo.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $provider = youtubeProviderWithFixtures();
    $input = $provider->resolveInput(\App\Providers\StashdUri::parse($fixture['source_uri']));
    $strategy = (new ProviderStrategySelector())->select($provider, StrategyPurpose::Discovery);

    expect($input->providerKey)->toBe($fixture['resolved_input']['provider_key'])
        ->and($strategy->key)->toBe($fixture['discovery']['strategy_key']);

    $items = $provider->discover($input, $strategy);
    expect($items)->toHaveCount($fixture['discovery']['initial_item_count'])
        ->and($items[0]->providerItemId)->toBe($fixture['discovery']['first_item_id'])
        ->and($items[0]->title)->toBe($fixture['discovery']['first_item_title']);
});

test('youtube provider resolveInput enriches a channel handle with real identity', function (): void {
    $provider = youtubeProviderWithFixtures();

    $resolved = $provider->resolveInput(\App\Providers\StashdUri::parse('https://www.youtube.com/@StashdDemo'));

    expect($resolved->providerInputId)->toBe('UCStashdDemoCh0012345678')
        ->and($resolved->sourceTitle)->toBe('Stashd Demo')
        ->and($resolved->sourceAvatarUri?->toString())->toBe('https://yt3.googleusercontent.com/stashd-demo-avatar.jpg')
        ->and($resolved->estimatedItemCount)->toBe(217);
});

test('youtube provider resolveInput stays network-free for video inputs', function (): void {
    $provider = youtubeProviderWithFixtures();

    $resolved = $provider->resolveInput(\App\Providers\StashdUri::parse('https://www.youtube.com/watch?v=demoVideo01'));

    expect($resolved->providerInputId)->toBe('demoVideo01')
        ->and($resolved->sourceTitle)->toBeNull()
        ->and($resolved->sourceAvatarUri)->toBeNull()
        ->and($resolved->estimatedItemCount)->toBeNull();
});

test('youtube strategy selector prefers rss for discovery and skips ytdlp without allow last resort', function (): void {
    $provider = youtubeProviderWithFixtures();
    $selector = new ProviderStrategySelector();

    $discovery = $selector->select($provider, StrategyPurpose::Discovery);
    expect($discovery->key)->toBe('youtube.rss')
        ->and($discovery->cost)->toBe(StrategyCost::Low);

    expect(fn () => $selector->select($provider, StrategyPurpose::Download))
        ->toThrow(\InvalidArgumentException::class);
});

test('youtube strategy selector keeps preferring rss for discovery even when a data api key is configured', function (): void {
    $provider = youtubeProviderWithFixtures(apiKey: 'test-api-key');
    $selector = new ProviderStrategySelector();

    $discovery = $selector->select($provider, StrategyPurpose::Discovery);

    expect($discovery->key)->toBe('youtube.rss')
        ->and($discovery->cost)->toBe(StrategyCost::Low);
});

test('youtube strategy selector prefers data api discovery for a backfill-intent selection when configured', function (): void {
    $provider = youtubeProviderWithFixtures(apiKey: 'test-api-key');
    $selector = new ProviderStrategySelector();

    $discovery = $selector->select(
        $provider,
        StrategyPurpose::Discovery,
        new StrategySelectionOptions(preferHighestCapability: true),
    );

    expect($discovery->key)->toBe('youtube.data_api_discovery')
        ->and($discovery->cost)->toBe(StrategyCost::Medium);
});

test('youtube strategy selector falls back to rss for a backfill-intent selection without a key', function (): void {
    $provider = youtubeProviderWithFixtures(apiKey: null);
    $selector = new ProviderStrategySelector();

    $discovery = $selector->select(
        $provider,
        StrategyPurpose::Discovery,
        new StrategySelectionOptions(preferHighestCapability: true),
    );

    expect($discovery->key)->toBe('youtube.rss');
});

test('youtube strategy selector prefers data api metadata when configured', function (): void {
    $provider = youtubeProviderWithFixtures(apiKey: 'test-api-key');
    $selector = new ProviderStrategySelector();

    $metadata = $selector->select($provider, StrategyPurpose::Metadata);
    expect($metadata->key)->toBe('youtube.data_api')
        ->and($metadata->cost)->toBe(StrategyCost::Medium);
});

test('youtube strategy selector skips metadata when data api key is not configured', function (): void {
    $provider = youtubeProviderWithFixtures(apiKey: null);
    $selector = new ProviderStrategySelector();

    expect(fn () => $selector->select($provider, StrategyPurpose::Metadata))
        ->toThrow(\InvalidArgumentException::class);
});

test('youtube strategy selector can include ytdlp when explicitly allowed and enabled', function (): void {
    $provider = youtubeProviderWithFixtures(realDownloads: true);
    $selector = new ProviderStrategySelector();

    $download = $selector->select(
        $provider,
        StrategyPurpose::Download,
        new StrategySelectionOptions(allowLastResort: true),
    );

    expect($download->key)->toBe('youtube.ytdlp')
        ->and($download->cost)->toBe(StrategyCost::LastResort);
});

test('fake provider strategy selection remains unchanged', function (): void {
    $provider = new \App\Providers\Fake\FakeProvider();
    $selector = new ProviderStrategySelector();

    expect($selector->select($provider, StrategyPurpose::Discovery)->key)->toBe('fake.feed');
});

test('youtube data api metadata strategy captures snippet description', function (): void {
    $fixturesDirectory = __DIR__ . '/../../../../fixtures/providers/youtube/http';
    $map = json_decode((string) file_get_contents($fixturesDirectory . '/map.json'), true, flags: JSON_THROW_ON_ERROR);
    $http = new FixtureProviderHttpClient($fixturesDirectory, $map);
    $strategy = new YouTubeDataApiMetadataStrategy(
        dataApiKey: new FixtureYouTubeDataApiKeyResolver('test-api-key'),
        http: $http,
    );

    $input = new \App\Providers\ResolvedInput(
        providerKey: 'youtube',
        inputType: 'video',
        sourceUri: \App\Providers\StashdUri::parse('https://www.youtube.com/watch?v=demoVideo01'),
        providerInputId: 'demoVideo01',
    );
    $item = new \App\Providers\Core\DiscoveredItem(
        providerItemId: 'demoVideo01',
        canonicalUri: \App\Providers\StashdUri::parse('https://www.youtube.com/watch?v=demoVideo01'),
        title: 'Demo Episode One',
    );

    $enriched = $strategy->enrich($input, $item);

    expect($enriched->description)->toBe('Fixture video description.');
});
