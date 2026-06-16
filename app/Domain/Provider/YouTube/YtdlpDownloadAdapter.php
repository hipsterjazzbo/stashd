<?php

declare(strict_types=1);

namespace App\Domain\Provider\YouTube;

use App\Domain\Provider\DownloadStrategyHandler;
use App\Domain\Provider\StashdUri;

/**
 * ytdlphp-backed download adapter boundary.
 *
 * Phase 3B ships a placeholder only. All future yt-dlp interaction must go through this adapter.
 */
interface YtdlpDownloadAdapter extends DownloadStrategyHandler
{
    /**
     * @return array<string, mixed>
     */
    public function probe(StashdUri $canonicalUri): array;
}
