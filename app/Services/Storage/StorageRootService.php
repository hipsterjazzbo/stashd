<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Config\StashdConfig;
use RuntimeException;

final readonly class StorageRootService
{
    public function __construct(
        private StashdConfig $config,
    ) {
    }

    /** @return list<string> */
    public function ensureDirectories(): array
    {
        $created = [];

        foreach ($this->requiredPaths() as $path) {
            if (is_dir($path)) {
                continue;
            }

            if (! mkdir($path, 0775, true) && ! is_dir($path)) {
                throw new RuntimeException("Stashd cannot create directory: {$path}");
            }

            $created[] = $path;
        }

        return $created;
    }

    /** @return list<string> */
    private function requiredPaths(): array
    {
        return [
            $this->config->dataPath,
            $this->config->backupsPath(),
            $this->config->vaultPath(),
            $this->config->broadcastsPath(),
            $this->config->tempPath(),
            $this->config->cachePath(),
            rtrim($this->config->tempPath(), '/') . '/downloads',
        ];
    }
}
