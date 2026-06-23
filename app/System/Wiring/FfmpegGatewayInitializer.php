<?php

declare(strict_types=1);

namespace App\System\Wiring;

use App\Transcoding\Ffmpeg\FfmpegGateway;
use App\Transcoding\Ffmpeg\FfmpegGatewayImpl;
use App\Transcoding\Ffmpeg\StubFfmpegGateway;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

use function Tempest\env;

final class FfmpegGatewayInitializer implements Initializer
{
    private static ?StubFfmpegGateway $testingGateway = null;

    public function initialize(Container $container): FfmpegGateway
    {
        if (env('ENVIRONMENT', 'local') === 'testing') {
            return self::$testingGateway ??= new StubFfmpegGateway();
        }

        return $container->get(FfmpegGatewayImpl::class);
    }
}
