<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Domain\Download\DownloaderInterface;
use App\Domain\Download\RoutingDownloader;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class DownloaderInitializer implements Initializer
{
    public function initialize(Container $container): DownloaderInterface
    {
        return $container->get(RoutingDownloader::class);
    }
}
