<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\Http\CurlProviderHttpClient;
use App\Providers\YouTube\YouTubeChannelIdResolver;

/**
 * Opt-in live YouTube channel resolution test — never run in normal CI.
 *
 * Requires STASHD_LIVE_PROVIDER_TESTS=1 and outbound network access to youtube.com.
 * Verifies the fixture-driven unit tests against a real handle's parsed output, per
 * the T2 verify caveat (fixtures always return the demo channel and cannot catch
 * real-world drift in YouTube's HTML/meta tags).
 */
test('live channel id resolver resolves a real handle to its own identity', function (): void {
    if (! filter_var(getenv('STASHD_LIVE_PROVIDER_TESTS') ?: '0', FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set STASHD_LIVE_PROVIDER_TESTS=1 to run live provider tests.');
    }

    $handle = getenv('STASHD_LIVE_PROVIDER_HANDLE') ?: 'mkbhd';
    $resolver = new YouTubeChannelIdResolver(new CurlProviderHttpClient());

    $resolved = $resolver->resolve('handle:' . $handle);

    fwrite(STDERR, sprintf(
        "[live-provider] handle=%s id=%s title=%s avatar=%s estimatedVideoCount=%s\n",
        $handle,
        $resolved->id,
        $resolved->title ?? 'null',
        $resolved->avatarUri ?? 'null',
        $resolved->estimatedVideoCount === null ? 'null' : (string) $resolved->estimatedVideoCount,
    ));

    expect($resolved->id)->toMatch('/^UC[\w-]{22}$/')
        ->and($resolved->title)->not->toBeNull()
        ->and($resolved->avatarUri)->not->toBeNull()
        ->and($resolved->estimatedVideoCount)->not->toBeNull()
        ->and($resolved->estimatedVideoCount)->toBeGreaterThan(0);
})->group('live');
