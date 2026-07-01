<?php

declare(strict_types=1);

namespace App\Broadcasts;

use Psr\Container\ContainerInterface;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers broadcast plugins by scanning for #[StashdBroadcast] attributes.
 * Populates BroadcastPluginRegistry with resolved plugin instances at boot.
 */
final class BroadcastPluginDiscoverer implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private ContainerInterface $container,
    ) {
        $this->discoveryItems = new DiscoveryItems();
    }

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(StashdBroadcast::class);

        if ($attribute === null) {
            return;
        }

        $this->discoveryItems->add($location, [
            'className' => $class->getName(),
            'name' => $attribute->name,
            'description' => $attribute->description,
        ]);
    }

    public function apply(): void
    {
        $registeredKeys = [];

        foreach ($this->discoveryItems as $meta) {
            try {
                $instance = $this->container->get($meta['className']);
            } catch (\Throwable $e) {
                error_log("[stashd] BroadcastPluginDiscoverer: failed to resolve {$meta['className']}: {$e->getMessage()}");

                continue;
            }

            if (! $instance instanceof BroadcastPlugin) {
                error_log("[stashd] BroadcastPluginDiscoverer: {$meta['className']} has #[StashdBroadcast] but does not implement BroadcastPlugin — skipping.");

                continue;
            }

            $broadcastKeys = $instance->broadcastKeys();

            foreach ($broadcastKeys as $key) {
                if (in_array($key, $registeredKeys, true)) {
                    error_log("[stashd] BroadcastPluginDiscoverer: duplicate broadcast key '{$key}' — skipping {$meta['className']}.");

                    continue 2;
                }
            }

            $registeredKeys = array_merge($registeredKeys, $broadcastKeys);

            BroadcastPluginRegistry::add(new DiscoveredPlugin(
                className: $meta['className'],
                name: $meta['name'],
                description: $meta['description'],
                broadcastKeys: $broadcastKeys,
                plugin: $instance,
            ));
        }
    }
}
