<?php

declare(strict_types=1);

namespace App\System\Wiring;

use App\Providers\YouTube\YouTubeYtdlpDownloadStrategy;
use App\Providers\YouTube\YtdlpDownloadAdapter;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class YtdlpDownloadAdapterInitializer implements Initializer
{
    public function initialize(Container $container): YtdlpDownloadAdapter
    {
        return $container->get(YouTubeYtdlpDownloadStrategy::class);
    }
}
