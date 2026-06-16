<?php

declare(strict_types=1);

namespace App\Providers;

/**
 * Download strategy boundary — Phase 3B defines the interface only.
 *
 * Real downloads, temp staging, and Vault writes are implemented in Phase 4.
 */
interface DownloadStrategyHandler
{
    public function strategyKey(): string;

    public function isAvailable(): bool;

    public function implementationName(): string;

    public function implementationVersion(): ?string;
}
