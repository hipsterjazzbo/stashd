<?php

declare(strict_types=1);

use Tests\IntegrationTestCase;

pest()->extend(IntegrationTestCase::class)
    ->beforeEach(function (): void {
        $root = dirname(__DIR__);
        $data = sys_get_temp_dir() . '/stashd-test/data';
        $media = sys_get_temp_dir() . '/stashd-test/media';

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
