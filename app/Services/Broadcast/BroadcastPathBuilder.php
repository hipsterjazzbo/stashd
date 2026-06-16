<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Config\StashdConfig;
use App\Services\Storage\PathSanitizer;
use InvalidArgumentException;

final readonly class BroadcastPathBuilder
{
    public function __construct(
        private StashdConfig $config,
    ) {
    }

    public function broadcastRoot(string $broadcastId): string
    {
        $safeId = PathSanitizer::sanitizeSegment($broadcastId);
        $path = rtrim($this->config->broadcastsPath(), '/') . '/' . $safeId;
        $this->assertWithinBroadcastsRoot($path);

        return $path;
    }

    public function broadcastFile(string $broadcastId, string ...$segments): string
    {
        $root = $this->broadcastRoot($broadcastId);
        $parts = [$root];

        foreach ($segments as $segment) {
            $parts[] = PathSanitizer::sanitizeBroadcastSegment($segment);
        }

        $path = implode('/', $parts);
        $this->assertWithinBroadcastsRoot($path);

        return $path;
    }

    public function relativeFile(string ...$segments): string
    {
        $parts = [];

        foreach ($segments as $segment) {
            $parts[] = PathSanitizer::sanitizeBroadcastSegment($segment);
        }

        return implode('/', $parts);
    }

    public function assertWithinBroadcastsRoot(string $path): void
    {
        $root = rtrim(str_replace('\\', '/', $this->config->broadcastsPath()), '/');
        $candidate = str_replace('\\', '/', $path);

        if ($candidate === $root || str_starts_with($candidate, $root . '/')) {
            return;
        }

        $resolvedRoot = realpath($root);
        $resolvedParent = realpath(dirname($path));

        if (
            $resolvedRoot !== false
            && $resolvedParent !== false
            && str_starts_with($resolvedParent, $resolvedRoot)
        ) {
            return;
        }

        throw new InvalidArgumentException('Broadcast path escapes storage root.');
    }
}
