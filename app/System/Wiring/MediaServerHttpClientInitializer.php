<?php

declare(strict_types=1);

namespace App\System\Wiring;

use App\MediaServers\Http\CurlMediaServerHttpClient;
use App\MediaServers\Http\FixtureMediaServerHttpClient;
use App\MediaServers\MediaServerHttpClient;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class MediaServerHttpClientInitializer implements Initializer
{
    public function initialize(Container $container): MediaServerHttpClient
    {
        $environment = getenv('ENVIRONMENT') ?: $_ENV['ENVIRONMENT'] ?? 'local';

        if ($environment === 'testing') {
            $fixturesDirectory = dirname(__DIR__, 3) . '/tests/fixtures/media_servers/http';
            $mapPath = $fixturesDirectory . '/map.json';
            $map = [];

            if (is_file($mapPath)) {
                $decoded = json_decode((string) file_get_contents($mapPath), true);
                $map = is_array($decoded) ? $decoded : [];
            }

            return new FixtureMediaServerHttpClient($fixturesDirectory, $map);
        }

        return new CurlMediaServerHttpClient();
    }
}
