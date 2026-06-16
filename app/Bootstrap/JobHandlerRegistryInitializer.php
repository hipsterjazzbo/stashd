<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Services\Job\Handlers\BootJobHandler;
use App\Services\Job\Handlers\BroadcastJobHandler;
use App\Services\Job\Handlers\CreateFromPreflightJobHandler;
use App\Services\Job\Handlers\DownloadJobHandler;
use App\Services\Job\Handlers\MediaServerJobHandler;
use App\Services\Job\Handlers\PreflightJobHandler;
use App\Services\Job\Handlers\StorageCheckJobHandler;
use App\Services\Job\Handlers\VerifyVaultJobHandler;
use App\Services\Job\JobHandlerRegistry;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class JobHandlerRegistryInitializer implements Initializer
{
    public function initialize(Container $container): JobHandlerRegistry
    {
        return new JobHandlerRegistry([
            $container->get(BootJobHandler::class),
            $container->get(PreflightJobHandler::class),
            $container->get(CreateFromPreflightJobHandler::class),
            $container->get(DownloadJobHandler::class),
            $container->get(StorageCheckJobHandler::class),
            $container->get(VerifyVaultJobHandler::class),
            $container->get(BroadcastJobHandler::class),
            $container->get(MediaServerJobHandler::class),
        ]);
    }
}
