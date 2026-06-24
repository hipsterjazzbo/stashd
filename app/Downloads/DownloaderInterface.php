<?php

declare(strict_types=1);

namespace App\Downloads;

/**
 * Download service boundary — all media acquisition must go through this interface.
 *
 * Phase 4B ships {@see Ytdlp\YtdlpDownloader} via {@see DelegatingDownloader}.
 * Phase 4A fake implementation remains for the fake provider and smoke tests.
 */
interface DownloaderInterface
{
    public function implementationName(): string;

    public function implementationVersion(): ?string;

    public function probe(): DownloadProbeResult;

    /**
     * @param ?callable(\Ytdlphp\DownloadProgress): void $onProgress Invoked
     *     with live progress where the implementation supports it. Not every
     *     implementation reports progress (e.g. the fake downloader writes
     *     its fixture instantly) — callers must not assume it will be called.
     */
    public function download(DownloadRequest $request, ?callable $onProgress = null): DownloadResult;
}
