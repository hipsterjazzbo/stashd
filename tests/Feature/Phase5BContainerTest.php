<?php

declare(strict_types=1);

test('container resolves broadcast and media server services', function (): void {
    expect($this->container->get(\App\Services\Broadcast\BroadcastLifecycleService::class))->not->toBeNull()
        ->and($this->container->get(\App\Services\Broadcast\Types\FilesystemSeriesBroadcastType::class))->not->toBeNull()
        ->and($this->container->get(\App\Services\MediaServer\MediaServerConnectionService::class))->not->toBeNull()
        ->and($this->container->get(\App\Services\Command\CommandHandlerRegistry::class)->handlerFor(\App\Domain\Command\CommandType::BroadcastRebuild))->not->toBeNull();
});
