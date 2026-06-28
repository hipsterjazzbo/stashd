<?php

declare(strict_types=1);

namespace App\Broadcasts;

/**
 * Wrapper DTO for a discovered broadcast plugin.
 *
 * Holds the plugin instance alongside its metadata from the #[StashdBroadcast]
 * attribute, plus a deduplicated list of all broadcast keys.
 */
final readonly class DiscoveredPlugin
{
    public function __construct(
        /** The concrete plugin class name. */
        public string $className,
        /** Plugin name from the #[StashdBroadcast] attribute. */
        public string $name,
        /** Plugin description from the #[StashdBroadcast] attribute. */
        public string $description,
        /** Deduplicated broadcast keys from the plugin. */
        public array $broadcastKeys,
    ) {
    }
}
