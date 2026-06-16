<?php

declare(strict_types=1);

namespace App\Domain\Download\Ytdlp;

use Ytdlphp\Metadata\VideoInfo;
use Ytdlphp\Options;

/**
 * Seam for ytdlphp — Stashd must not spawn processes outside this boundary.
 */
interface YtdlpGateway
{
    public function probe(): YtdlpProbeResult;

    public function extractInfo(string $url, string $workingDirectory): VideoInfo;

    public function download(string $url, string $workingDirectory, Options $options): \Ytdlphp\DownloadResult;
}
