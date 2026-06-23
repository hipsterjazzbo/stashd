<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\YtdlpConfig;

/**
 * ytdlp.config.php is a plain script, not a class -- requiring it fresh
 * (rather than resolving YtdlpConfig from the container) is the only way to
 * exercise its env-dependent default computation in isolation, independent
 * of whatever's already cached in the container for this test process.
 */
function loadYtdlpConfigDefault(): YtdlpConfig
{
    return require __DIR__ . '/../../../app/Config/ytdlp.config.php';
}

function withEnv(array $values, callable $callback): mixed
{
    $previous = [];

    foreach ($values as $key => $value) {
        $previous[$key] = getenv($key);

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }

    try {
        return $callback();
    } finally {
        foreach ($previous as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}

test('real downloads default on outside of the testing environment when unset', function (): void {
    $config = withEnv(
        ['ENVIRONMENT' => 'local', 'STASHD_REAL_DOWNLOADS_ENABLED' => null],
        fn () => loadYtdlpConfigDefault(),
    );

    expect($config->realDownloadsEnabled())->toBeTrue();
});

test('real downloads default off in the testing environment when unset', function (): void {
    $config = withEnv(
        ['ENVIRONMENT' => 'testing', 'STASHD_REAL_DOWNLOADS_ENABLED' => null],
        fn () => loadYtdlpConfigDefault(),
    );

    expect($config->realDownloadsEnabled())->toBeFalse();
});

test('an explicit STASHD_REAL_DOWNLOADS_ENABLED always overrides the environment default', function (): void {
    $forcedOffOutsideTesting = withEnv(
        ['ENVIRONMENT' => 'local', 'STASHD_REAL_DOWNLOADS_ENABLED' => '0'],
        fn () => loadYtdlpConfigDefault(),
    );
    $forcedOnDuringTesting = withEnv(
        ['ENVIRONMENT' => 'testing', 'STASHD_REAL_DOWNLOADS_ENABLED' => '1'],
        fn () => loadYtdlpConfigDefault(),
    );

    expect($forcedOffOutsideTesting->realDownloadsEnabled())->toBeFalse()
        ->and($forcedOnDuringTesting->realDownloadsEnabled())->toBeTrue();
});
