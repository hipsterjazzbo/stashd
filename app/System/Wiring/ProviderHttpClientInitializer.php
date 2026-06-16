<?php

declare(strict_types=1);

namespace App\System\Wiring;

use App\Providers\Http\CurlProviderHttpClient;
use App\Providers\Http\FixtureProviderHttpClient;
use App\Providers\ProviderHttpClient;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class ProviderHttpClientInitializer implements Initializer
{
    public function initialize(Container $container): ProviderHttpClient
    {
        $environment = getenv('ENVIRONMENT') ?: $_ENV['ENVIRONMENT'] ?? 'local';

        if ($environment === 'testing') {
            $fixturesDirectory = dirname(__DIR__, 3) . '/tests/fixtures/providers/youtube/http';
            $mapPath = $fixturesDirectory . '/map.json';
            $map = [];

            if (is_file($mapPath)) {
                $decoded = json_decode((string) file_get_contents($mapPath), true);
                $map = is_array($decoded) ? $decoded : [];
            }

            return new FixtureProviderHttpClient($fixturesDirectory, $map);
        }

        return new CurlProviderHttpClient();
    }
}
