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

    expect($resolver->resolve('UCStashdDemoCh0012345678')->id)->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver resolves a handle to a channel id', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    expect($resolver->resolve('handle:StashdDemo')->id)->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver resolves a custom channel name to a channel id', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    expect($resolver->resolve('c:StashdDemo')->id)->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver resolves a legacy username to a channel id', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    expect($resolver->resolve('user:StashdDemo')->id)->toBe('UCStashdDemoCh0012345678');
});

test('youtube channel id resolver ignores a decoy channel id and prefers the canonical link', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    $channel = $resolver->resolve('handle:StashdDemo');

    expect($channel->id)->toBe('UCStashdDemoCh0012345678')
        ->and($channel->id)->not->toBe('UCDecoyChannelId00000001');
});

test('youtube channel id resolver captures the real display name, avatar and approximate video count', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    $channel = $resolver->resolve('handle:StashdDemo');

    expect($channel->title)->toBe('Stashd Demo')
        ->and($channel->avatarUri)->toBe('https://yt3.googleusercontent.com/stashd-demo-avatar.jpg')
        ->and($channel->estimatedVideoCount)->toBe(217);
});

test('youtube channel id resolver ignores a decoy video count from a featured channels shelf', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    $channel = $resolver->resolve('handle:StashdDemo');

    expect($channel->estimatedVideoCount)->toBe(217)
        ->and($channel->estimatedVideoCount)->not->toBe(999);
});

test('youtube channel id resolver falls back to externalId and still ignores the decoy channel id', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    $channel = $resolver->resolve('c:StashdExternalIdOnlyChannel');

    expect($channel->id)->toBe('UCStashdDemoCh0012345678')
        ->and($channel->title)->toBeNull()
        ->and($channel->avatarUri)->toBeNull()
        ->and($channel->estimatedVideoCount)->toBeNull();
});

test('youtube channel id resolver parses abbreviated video counts as estimates', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    $channel = $resolver->resolve('c:StashdAbbreviatedCountChannel');

    expect($channel->estimatedVideoCount)->toBe(1200);
});

test('youtube channel id resolver treats the owner as unresolvable when no priority signal is present', function (): void {
    $resolver = youtubeChannelIdResolverWithFixtures();

    try {
        $resolver->resolve('c:StashdUnresolvableChannel');
    } catch (ProviderException $exception) {
        expect($exception->errorCode)->toBe('channel_resolution_failed');

        return;
    }

    throw new \RuntimeException('Expected ProviderException was not thrown.');
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
