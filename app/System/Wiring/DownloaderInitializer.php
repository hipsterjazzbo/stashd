<?php

declare(strict_types=1);

namespace App\System\Wiring;

use App\Downloads\DelegatingDownloader;
use App\Downloads\DownloaderInterface;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class DownloaderInitializer implements Initializer
{
    public function initialize(Container $container): DownloaderInterface
    {
        return $container->get(DelegatingDownloader::class);
    }
}
