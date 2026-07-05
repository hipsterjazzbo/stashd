<?php

declare(strict_types=1);

namespace App\System\Wiring;

use App\Config\StashdConfig;
use App\System\Event\MercureSecret;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class MercureHubInitializer implements Initializer
{
    public function initialize(Container $container): HubInterface
    {
        $config = $container->get(StashdConfig::class);

        $tokenProvider = new FactoryTokenProvider(
            factory: new LcobucciFactory(MercureSecret::resolve()),
            publish: ['*'],
        );

        return new Hub(
            url: "http://127.0.0.1:{$config->httpPort}/.well-known/mercure",
            jwtProvider: $tokenProvider,
        );
    }
}
