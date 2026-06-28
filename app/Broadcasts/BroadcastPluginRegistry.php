<?php

declare(strict_types=1);

namespace App\Broadcasts;

/**
 * Registry of discovered broadcast plugins.
 *
 * Populated at boot by BroadcastPluginDiscoverer.
 * Accessed at runtime to enumerate available broadcast formats.
 */
final class BroadcastPluginRegistry
{
    /**
     * @var list<DiscoveredPlugin>
     */
    private static array $plugins = [];

    /**
     * @var list<string> Deduplicated broadcast keys across all plugins.
     */
    private static array $broadcastKeys = [];

    public static function reset(): void
    {
        self::$plugins = [];
        self::$broadcastKeys = [];
    }

    public static function add(DiscoveredPlugin $plugin): void
    {
        self::$plugins[] = $plugin;

        foreach ($plugin->broadcastKeys as $key) {
            if (! in_array($key, self::$broadcastKeys, true)) {
                self::$broadcastKeys[] = $key;
            }
        }
    }

    /** @return list<DiscoveredPlugin> */
    public static function all(): array
    {
        return self::$plugins;
    }

    /** @return list<string> */
    public static function broadcastKeys(): array
    {
        return self::$broadcastKeys;
    }

    /**
     * Get a plugin by its broadcast key.
     */
    public static function findByKey(string $key): ?DiscoveredPlugin
    {
        foreach (self::$plugins as $plugin) {
            if (in_array($key, $plugin->broadcastKeys, true)) {
                return $plugin;
            }
        }

        return null;
    }
}
