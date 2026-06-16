<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Domain\Download\Ytdlp\StubYtdlpGateway;
use App\Domain\Download\Ytdlp\YtdlpGateway;
use App\Domain\Download\Ytdlp\YtdlpGatewayImpl;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

use function Tempest\env;

final class YtdlpGatewayInitializer implements Initializer
{
    private static ?StubYtdlpGateway $testingGateway = null;

    public function initialize(Container $container): YtdlpGateway
    {
        if (env('ENVIRONMENT', 'local') === 'testing') {
            return self::$testingGateway ??= new StubYtdlpGateway();
        }

        return $container->get(YtdlpGatewayImpl::class);
    }
}
