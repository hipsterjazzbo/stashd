<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Config\FfmpegConfig;
use App\Transcoding\Ffmpeg\FfmpegAudioProfile;
use App\Transcoding\Ffmpeg\FfmpegGatewayImpl;
use App\Transcoding\Ffmpeg\FfmpegProgress;
use Tempest\Process\GenericProcessExecutor;

/**
 * Opt-in live ffmpeg tests — never run in normal CI.
 *
 * Requires STASHD_LIVE_FFMPEG_TESTS=1 and a working ffmpeg binary on PATH.
 * Separate from STASHD_LIVE_DOWNLOAD_TESTS since this exercises a different
 * binary/boundary.
 *
 * Does not separately re-verify Symfony Process's timeout-kill mechanism
 * (Tempest\Process\InvokedSystemProcess::wait() delegates straight to it) --
 * forcing a deterministic timeout without flakiness needs a source clip long
 * enough to guarantee a slow encode, which would make this test slow on every
 * run. That mechanism is well-established infrastructure outside this
 * feature; what these tests verify is specific to this gateway: a real
 * ffmpeg invocation runs to completion, progress parsing works against real
 * `-progress` output (not the stub), and the output file is valid.
 */
function liveFfmpegSkipUnlessEnabled(\Tests\IntegrationTestCase $test): void
{
    if (! filter_var(getenv('STASHD_LIVE_FFMPEG_TESTS') ?: '0', FILTER_VALIDATE_BOOL)) {
        $test->markTestSkipped('Set STASHD_LIVE_FFMPEG_TESTS=1 to run live ffmpeg tests.');
    }
}

function liveFfmpegGateway(): FfmpegGatewayImpl
{
    $config = new FfmpegConfig(binary: getenv('STASHD_FFMPEG_BINARY') ?: 'ffmpeg', timeoutSeconds: 30);

    return new FfmpegGatewayImpl($config, new GenericProcessExecutor());
}

/** Generates a tiny silent audio clip with ffmpeg itself -- no binary fixture to commit. */
function liveFfmpegSyntheticSource(int $seconds = 2): string
{
    $path = sys_get_temp_dir() . '/stashd-live-ffmpeg-source-' . bin2hex(random_bytes(4)) . '.wav';
    $executor = new GenericProcessExecutor();
    $result = $executor->run([
        getenv('STASHD_FFMPEG_BINARY') ?: 'ffmpeg',
        '-y', '-loglevel', 'error',
        '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo',
        '-t', (string) $seconds,
        $path,
    ]);

    if (! $result->successful() || ! is_file($path)) {
        throw new \RuntimeException('Could not generate a synthetic ffmpeg source clip: ' . $result->errorOutput);
    }

    return $path;
}

test('live ffmpeg gateway probe reaches the real binary', function (): void {
    liveFfmpegSkipUnlessEnabled($this);

    $probe = liveFfmpegGateway()->probe();

    expect($probe->available)->toBeTrue()
        ->and($probe->version)->not->toBeNull();
})->group('live');

test('live ffmpeg gateway transcodes a real source with live progress callbacks', function (): void {
    liveFfmpegSkipUnlessEnabled($this);

    $source = liveFfmpegSyntheticSource(2);
    $destination = sys_get_temp_dir() . '/stashd-live-ffmpeg-output-' . bin2hex(random_bytes(4)) . '.mp3';
    $progressUpdates = [];

    try {
        $result = liveFfmpegGateway()->transcodeToMp3(
            sourcePath: $source,
            destinationPath: $destination,
            profile: FfmpegAudioProfile::v1Default(),
            totalSeconds: 2,
            onProgress: function (FfmpegProgress $progress) use (&$progressUpdates): void {
                $progressUpdates[] = $progress;
            },
        );

        expect($result->successful)->toBeTrue()
            ->and(is_file($destination))->toBeTrue()
            ->and(filesize($destination))->toBeGreaterThan(0)
            ->and($progressUpdates)->not->toBeEmpty();

        foreach ($progressUpdates as $progress) {
            expect($progress->percent)->toBeGreaterThanOrEqual(0.0)
                ->and($progress->percent)->toBeLessThanOrEqual(100.0);
        }
    } finally {
        @unlink($source);
        @unlink($destination);
    }
})->group('live');
