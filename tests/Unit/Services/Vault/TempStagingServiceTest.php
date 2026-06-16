<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vault;

use App\Config\StashdConfig;
use App\Domain\Support\PrefixedUlid;
use App\Services\Vault\TempStagingService;

test('temp staging reuses job directory by cleaning stale partial files', function (): void {
    $media = sys_get_temp_dir() . '/stashd-temp-staging/media';
    $config = new StashdConfig(
        dataPath: sys_get_temp_dir() . '/stashd-temp-staging/data',
        mediaPath: $media,
        publicUrl: 'http://localhost:8474',
        logFormat: 'text',
        puid: 1000,
        pgid: 1000,
        umask: '0022',
        httpPort: '8474',
    );
    $service = new TempStagingService($config);
    $jobId = PrefixedUlid::parse('job_01J00000000000000000000001');
    $path = $service->createWorkDirectory($jobId);
    file_put_contents($path . '/partial.bin', 'partial');
    $service->markFailed($path);

    $cleanPath = $service->createWorkDirectory($jobId);

    expect($cleanPath)->toBe($path)
        ->and(file_exists($path . '/partial.bin'))->toBeFalse()
        ->and(file_exists($path . '/.failed'))->toBeFalse();
});
