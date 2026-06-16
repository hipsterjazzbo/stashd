<?php

declare(strict_types=1);

namespace App\Downloads;

use App\Downloads\Fake\FakeDownloader;
use App\Downloads\Ytdlp\YtdlpDownloader;

/**
 * Routes downloads to the fake provider downloader or ytdlphp based on provider identity.
 */
final readonly class DelegatingDownloader implements DownloaderInterface
{
    public function __construct(
        private FakeDownloader $fake,
        private YtdlpDownloader $ytdlp,
    ) {
    }

    public function implementationName(): string
    {
        return 'routing';
    }

    public function implementationVersion(): ?string
    {
        return null;
    }

    public function probe(): DownloadProbeResult
    {
        $fake = $this->fake->probe();
        $ytdlp = $this->ytdlp->probe();

        return new DownloadProbeResult(
            available: $fake->available || $ytdlp->available,
            implementation: $this->implementationName(),
            implementationVersion: $ytdlp->implementationVersion,
            message: $ytdlp->available ? null : $ytdlp->message,
        );
    }

    public function download(DownloadRequest $request): DownloadResult
    {
        if ($request->providerKey === 'fake') {
            return $this->fake->download($request);
        }

        return $this->ytdlp->download($request);
    }
}
