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

    public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo;

    /**
     * Extracts playlist/channel metadata as a single JSON object (`-J`); the
     * per-entry list lives in the returned VideoInfo's `raw['entries']`.
     */
    public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo;

    /**
     * @param ?callable(DownloadProgress): void $onProgress
     */
    public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult;
}
