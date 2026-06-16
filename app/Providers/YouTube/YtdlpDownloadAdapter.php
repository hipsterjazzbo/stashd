<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Providers\DownloadStrategyHandler;
use App\Providers\StashdUri;

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
