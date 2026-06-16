<?php

declare(strict_types=1);

namespace App\Services\Vault;

use App\Config\StashdConfig;
use App\Domain\Support\PrefixedUlid;
use RuntimeException;

final readonly class TempStagingService
{
    public function __construct(
        private StashdConfig $config,
    ) {
    }

    public function createWorkDirectory(PrefixedUlid $jobId): string
    {
        $path = rtrim($this->config->tempPath(), '/') . '/downloads/' . $jobId->toString();

        if (is_dir($path)) {
            $this->removeDirectory($path);
        }

        if (! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create temp download directory: {$path}");
        }

        return $path;
    }

    public function cleanupSuccess(string $path): void
    {
        if (is_dir($path)) {
            $this->removeDirectory($path);
        }
    }

    public function markFailed(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $marker = rtrim($path, '/') . '/.failed';
        file_put_contents($marker, gmdate('c'));
    }

    private function removeDirectory(string $path): void
    {
        $entries = scandir($path);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;

            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
