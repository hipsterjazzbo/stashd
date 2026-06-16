<?php

declare(strict_types=1);

namespace App\Domain\Download;

/**
 * Download service boundary — all media acquisition must go through this interface.
 *
 * Phase 4B ships {@see Ytdlp\YtdlpDownloader} via {@see RoutingDownloader}.
 * Phase 4A fake implementation remains for the fake provider and smoke tests.
 */
interface DownloaderInterface
{
    public function implementationName(): string;

    public function implementationVersion(): ?string;

    public function probe(): DownloadProbeResult;

    public function download(DownloadRequest $request): DownloadResult;
}
