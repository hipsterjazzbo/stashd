<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Services\Command\CommandHandlerRegistry;
use App\Services\Command\Handlers\AssetVerifyCommandHandler;
use App\Services\Command\Handlers\BroadcastPlanCommandHandler;
use App\Services\Command\Handlers\BroadcastPruneCommandHandler;
use App\Services\Command\Handlers\BroadcastRebuildCommandHandler;
use App\Services\Command\Handlers\BroadcastTriggerCommandHandler;
use App\Services\Command\Handlers\BroadcastVerifyCommandHandler;
use App\Services\Command\Handlers\ItemDownloadCommandHandler;
use App\Services\Command\Handlers\MediaServerListLibrariesCommandHandler;
use App\Services\Command\Handlers\MediaServerTestConnectionCommandHandler;
use App\Services\Command\Handlers\StashCreateFromPreflightCommandHandler;
use App\Services\Command\Handlers\StashPreflightCommandHandler;
use App\Services\Command\Handlers\SystemStorageCheckCommandHandler;
use App\Services\Command\Handlers\SystemVerifyVaultCommandHandler;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class CommandHandlerRegistryInitializer implements Initializer
{
    public function initialize(Container $container): CommandHandlerRegistry
    {
        return new CommandHandlerRegistry([
            $container->get(StashPreflightCommandHandler::class),
            $container->get(StashCreateFromPreflightCommandHandler::class),
            $container->get(ItemDownloadCommandHandler::class),
            $container->get(SystemStorageCheckCommandHandler::class),
            $container->get(SystemVerifyVaultCommandHandler::class),
            $container->get(AssetVerifyCommandHandler::class),
            $container->get(BroadcastPlanCommandHandler::class),
            $container->get(BroadcastRebuildCommandHandler::class),
            $container->get(BroadcastVerifyCommandHandler::class),
            $container->get(BroadcastPruneCommandHandler::class),
            $container->get(BroadcastTriggerCommandHandler::class),
            $container->get(MediaServerTestConnectionCommandHandler::class),
            $container->get(MediaServerListLibrariesCommandHandler::class),
        ]);
    }
}
