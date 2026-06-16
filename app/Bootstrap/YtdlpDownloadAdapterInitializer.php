<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Domain\Provider\YouTube\YtdlpDownloadAdapter;
use App\Domain\Provider\YouTube\YtdlphpDownloadAdapter;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class YtdlpDownloadAdapterInitializer implements Initializer
{
    public function initialize(Container $container): YtdlpDownloadAdapter
    {
        return $container->get(YtdlphpDownloadAdapter::class);
    }
}
