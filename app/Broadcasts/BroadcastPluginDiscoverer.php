<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Broadcasts\BroadcastPlugin;
use App\Broadcasts\BroadcastPluginRegistry;
use App\Broadcasts\DiscoveredPlugin;
use Psr\Container\ContainerInterface;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers broadcast plugins by scanning for #[StashdBroadcast] attributes
 * across Tempest's configured discovery locations.
 *
 * Populates BroadcastPluginRegistry with discovered plugins.
 */
final class BroadcastPluginDiscoverer implements Discovery
{
    /** @var list<DiscoveredPlugin> Deduplicated plugins found so far. */
    private static array $collected = [];

    /** @var list<string> Broadcast keys already registered (for dedup). */
    private static array $registeredKeys = [];

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $attribute = $class->getAttribute(StashdBroadcast::class);

        if ($attribute === null) {
            return;
        }

        // Store class name; resolve instance in apply() when the container is ready.
        self::$collected[] = new DiscoveredPlugin(
            className: $class->getName(),
            name: $attribute->name,
            description: $attribute->description,
            broadcastKeys: [], // resolved later in apply()
        );
    }

    public function apply(): void
    {
        foreach (self::$collected as $pluginMeta) {
            // Resolve broadcast keys by reading the plugin's broadcastKeys() method.
            // We need an instance for this, so resolve from container.
            try {
                $instance = $this->container->get($pluginMeta->className);
            } catch (\Throwable $e) {
                error_log("[stashd] BroadcastPluginDiscoverer: failed to resolve {$pluginMeta->className}: {$e->getMessage()}");

                continue;
            }

            if (! $instance instanceof BroadcastPlugin) {
                error_log("[stashd] BroadcastPluginDiscoverer: class {$pluginMeta->className} has #[StashdBroadcast] but does not implement BroadcastPlugin — skipping.");

                continue;
            }

            $broadcastKeys = $instance->broadcastKeys();

            // Validate no duplicate keys.
            foreach ($broadcastKeys as $key) {
                if (in_array($key, self::$registeredKeys, true)) {
                    error_log("[stashd] BroadcastPluginDiscoverer: duplicate broadcast key '{$key}' — skipping plugin '{$pluginMeta->className}'.");

                    continue 2;
                }
            }

            self::$registeredKeys = array_merge(self::$registeredKeys, $broadcastKeys);

            // Update with resolved keys.
            $resolved = new DiscoveredPlugin(
                className: $pluginMeta->className,
                name: $pluginMeta->name,
                description: $pluginMeta->description,
                broadcastKeys: $broadcastKeys,
            );

            BroadcastPluginRegistry::add($resolved);
        }

        // Reset for next discovery cycle.
        self::$collected = [];
        self::$registeredKeys = [];
    }

    public function getItems(): \Tempest\Discovery\DiscoveryItems
    {
        return new \Tempest\Discovery\DiscoveryItems();
    }

    public function setItems(\Tempest\Discovery\DiscoveryItems $items): void
    {
    }

    public static function reset(): void
    {
        self::$collected = [];
        self::$registeredKeys = [];
    }
}
