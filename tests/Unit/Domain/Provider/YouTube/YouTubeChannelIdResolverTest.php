<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider\YouTube;

use App\Providers\Http\FixtureProviderHttpClient;
use App\Providers\ProviderException;
use App\Providers\YouTube\YouTubeChannelIdResolver;

function youtubeChannelIdResolverWithFixtures(): YouTubeChannelIdResolver
{
    $fixturesDirectory = __DIR__ . '/../../../../fixtures/providers/youtube/http';
    $map = json_decode((string) file_get_contents($fixturesDirectory . '/map.json'), true, flags: JSON_THROW_ON_ERROR);

    return new YouTubeChannelIdResolver(new FixtureProviderHttpClient($fixturesDirectory, $map));
}

test('youtube channel id resolver returns a channel id unchanged', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    expect($resolver->resolve('UCStashdDemoCh0012345678'))->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver resolves a handle to a channel id', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    expect($resolver->resolve('handle:StashdDemo'))->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver resolves a custom channel name to a channel id', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    expect($resolver->resolve('c:StashdDemo'))->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver resolves a legacy username to a channel id', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    expect($resolver->resolve('user:StashdDemo'))->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver rejects an unsupported identifier prefix', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    try {
        $resolver->resolve('playlist:StashdDemo');
    } catch (ProviderException $exception) {
        expect($exception->errorCode)->toBe('invalid_channel_identifier');

        return;
    }

    throw new \RuntimeException('Expected ProviderException was not thrown.');
});

test('youtube channel id resolver reports a stable, secret-safe error when the channel page is unavailable', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    try {
        $resolver->resolve('c:StashdUnmappedChannel');
    } catch (ProviderException $exception) {
        expect($exception->errorCode)->toBe('channel_unavailable')
            ->and($exception->getMessage())->not->toContain('<')
            ->and($exception->getMessage())->not->toContain('http');

        return;
    }

    throw new \RuntimeException('Expected ProviderException was not thrown.');
});
