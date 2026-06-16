<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Downloads\Ytdlp\YtdlpGatewayImpl;

/**
 * Opt-in live ytdlphp download tests — never run in normal CI.
 *
 * Requires STASHD_LIVE_DOWNLOAD_TESTS=1 and a working yt-dlp binary on PATH.
 */
test('live ytdlp gateway probe reaches yt-dlp binary', function (): void {
    if (! filter_var(getenv('STASHD_LIVE_DOWNLOAD_TESTS') ?: '0', FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set STASHD_LIVE_DOWNLOAD_TESTS=1 to run live download tests.');
    }

    $config = $this->container->get(\App\Config\YtdlpConfig::class);
    $gateway = new YtdlpGatewayImpl($config);
    $probe = $gateway->probe();

    expect($probe->available)->toBeTrue()
        ->and($probe->version)->not->toBeNull();
})->group('live');
