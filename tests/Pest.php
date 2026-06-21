<?php

declare(strict_types=1);

use App\Auth\AuthService;
use Tempest\Framework\Testing\Http\TestResponseHelper;
use Tempest\Http\Cookie\Cookie;
use Tests\IntegrationTestCase;

// Defined here (global namespace) rather than in AuthTest.php so it's loaded
// by every --parallel worker process regardless of which test files that
// worker is assigned — PHP falls back to the global namespace for functions
// not found in the caller's own namespace.
function useSessionCookieFrom(TestResponseHelper $response): void
{
    $values = $response->response->getHeader('set-cookie')?->values ?? [];

    foreach ($values as $value) {
        $cookie = Cookie::createFromString($value);

        if ($cookie->key === AuthService::SESSION_COOKIE) {
            $_COOKIE[AuthService::SESSION_COOKIE] = $cookie->value;

            return;
        }
    }

    throw new RuntimeException('Response did not set a ' . AuthService::SESSION_COOKIE . ' cookie.');
}

// Computed and applied once, at file-load time, before the first test's
// FrameworkKernel boots: database/storage config is resolved eagerly during
// boot (not lazily on first use), so beforeEach() is too late to redirect it
// for test 1 — by test 2 the putenv() calls below have already mutated the
// process-wide env, which is why a per-test-only version of this masked the
// bug after the first test. TEST_TOKEN is ParaTest's per-worker identifier
// (unset outside --parallel), mirrored from Tempest's own internalStorage
// keying so each parallel worker gets an isolated data dir/db file.
$worker = getenv('TEST_TOKEN') ?: 'default';
$data = sys_get_temp_dir() . '/stashd-test/' . $worker . '/data';
$media = sys_get_temp_dir() . '/stashd-test/' . $worker . '/media';

foreach ([$data, $media] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

putenv('STASHD_DATA_PATH=' . $data);
putenv('STASHD_MEDIA_PATH=' . $media);
putenv('DB_DATABASE=' . $data . '/stashd.sqlite');
$_ENV['STASHD_DATA_PATH'] = $data;
$_ENV['STASHD_MEDIA_PATH'] = $media;
$_ENV['DB_DATABASE'] = $data . '/stashd.sqlite';

pest()->extend(IntegrationTestCase::class)
    ->beforeEach(function () use ($media): void {
        $wipe = null;
        $wipe = static function (string $directory) use (&$wipe): void {
            if (! is_dir($directory)) {
                return;
            }

            $entries = scandir($directory) ?: [];

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory . '/' . $entry;

                if (is_dir($path)) {
                    $wipe($path);
                    rmdir($path);
                } else {
                    unlink($path);
                }
            }
        };

        $wipe($media);

        if (! is_dir($media)) {
            mkdir($media, 0775, true);
        }

        // Tests that exercise the cookie-authenticated session (AuthTest,
        // Phase2HardeningTest) write directly to this superglobal since
        // Tempest's request mapper reads cookies from it. Pest runs every
        // test in the same process, so it must not leak between tests.
        $_COOKIE = [];

        $this->useTestingDatabase();
        $this->database->reset();

        $sqlite = $this->container->get(\Tempest\Database\Config\SQLiteConfig::class);
        $this->container->get(\App\System\Boot\SqliteConfigurator::class)->configure($sqlite);
    })
    ->in('Feature');
