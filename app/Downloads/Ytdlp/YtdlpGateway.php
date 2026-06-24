<?php

declare(strict_types=1);

namespace App\Downloads\Ytdlp;

use Ytdlphp\DownloadProgress;
use Ytdlphp\Metadata\VideoInfo;
use Ytdlphp\Options;

/**
 * Seam for ytdlphp — Stashd must not spawn processes outside this boundary.
 */
interface YtdlpGateway
{
    public function probe(): YtdlpProbeResult;

    public function extractInfo(string $url, string $workingDirectory): VideoInfo;

    /**
     * @param ?callable(DownloadProgress): void $onProgress
     */
    public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult;
}
