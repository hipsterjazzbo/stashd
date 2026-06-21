<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider\YouTube;

use App\Providers\Core\DiscoveredItem;
use App\Providers\Http\FixtureProviderHttpClient;
use App\Providers\ProviderException;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;
use App\Providers\YouTube\FixtureYouTubeDataApiKeyResolver;
use App\Providers\YouTube\YouTubeDataApiDiscoveryStrategy;

function youtubeDataApiDiscoveryStrategyWithFixtures(?string $apiKey = 'test-api-key'): YouTubeDataApiDiscoveryStrategy
{
    $fixturesDirectory = __DIR__ . '/../../../../fixtures/providers/youtube/http';
    $map = json_decode((string) file_get_contents($fixturesDirectory . '/map.json'), true, flags: JSON_THROW_ON_ERROR);

    return new YouTubeDataApiDiscoveryStrategy(
        dataApiKey: new FixtureYouTubeDataApiKeyResolver($apiKey),
        http: new FixtureProviderHttpClient($fixturesDirectory, $map),
    );
}

function youtubeChannelResolvedInput(): ResolvedInput
{
    return new ResolvedInput(
        providerKey: 'youtube',
        inputType: 'channel',
        sourceUri: StashdUri::parse('https://www.youtube.com/channel/UCStashdDemoCh0012345678'),
        providerInputId: 'UCStashdDemoCh0012345678',
    );
}

test('youtube data api discovery strategy throws when no key is configured', function (): void {
    $strategy = youtubeDataApiDiscoveryStrategyWithFixtures(apiKey: null);

    try {
        $strategy->discover(youtubeChannelResolvedInput());
    } catch (ProviderException $exception) {
        expect($exception->errorCode)->toBe('data_api_unavailable');

        return;
    }

    throw new \RuntimeException('Expected ProviderException was not thrown.');
});

test('youtube data api discovery strategy pages through the uploads playlist and returns more than fifteen items', function (): void {
    $strategy = youtubeDataApiDiscoveryStrategyWithFixtures();

    $items = $strategy->discover(youtubeChannelResolvedInput());

    expect($items)->toHaveCount(18)
        ->and(count($items))->toBeGreaterThan(15);

    $ids = array_map(static fn (DiscoveredItem $item): string => $item->providerItemId, $items);
    expect($ids)->toBe([
        'StashdVid01', 'StashdVid02', 'StashdVid03', 'StashdVid04', 'StashdVid05',
        'StashdVid06', 'StashdVid07', 'StashdVid08', 'StashdVid09', 'StashdVid10',
        'StashdVid11', 'StashdVid12', 'StashdVid13', 'StashdVid14', 'StashdVid15',
        'StashdVid16', 'StashdVid17', 'StashdVid18',
    ]);
});

test('youtube data api discovery strategy classifies every item with a video type', function (): void {
    $strategy = youtubeDataApiDiscoveryStrategyWithFixtures();

    $items = $strategy->discover(youtubeChannelResolvedInput());
    $byId = [];
    foreach ($items as $item) {
        $byId[$item->providerItemId] = $item;
    }

    expect($byId['StashdVid01']->contentType)->toBe('regular')
        ->and($byId['StashdVid01']->durationSeconds)->toBe(600)
        ->and($byId['StashdVid15']->contentType)->toBe('short')
        ->and($byId['StashdVid16']->contentType)->toBe('short')
        ->and($byId['StashdVid17']->contentType)->toBe('live')
        ->and($byId['StashdVid18']->contentType)->toBe('premiere');

    foreach ($items as $item) {
        expect($item->contentType)->not->toBeNull();
    }
});

test('youtube data api discovery strategy captures title, description and thumbnail from playlist items', function (): void {
    $strategy = youtubeDataApiDiscoveryStrategyWithFixtures();

    $items = $strategy->discover(youtubeChannelResolvedInput());
    $first = $items[0];

    expect($first->title)->toBe('Stashd Video 1')
        ->and($first->description)->toBe('Desc 1')
        ->and($first->thumbnailUri?->toString())->toBe('https://i.ytimg.com/vi/StashdVid01/hqdefault.jpg')
        ->and($first->canonicalUri->toString())->toContain('StashdVid01');
});
