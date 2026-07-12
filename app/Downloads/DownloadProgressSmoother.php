<?php

declare(strict_types=1);

namespace App\Downloads;

use App\Jobs\JobProgressUpdate;
use Ytdlphp\DownloadProgress;

final class DownloadProgressSmoother
{
    private int $stream = 0;

    private float $lastPercent = 0.0;

    public function update(DownloadProgress $progress): JobProgressUpdate
    {
        $percent = min(100.0, max(0.0, $progress->percent ?? $this->lastPercent));

        // ponytail: yt-dlp reports no stream identifier; a reset after a
        // completed stream is the smallest reliable signal for audio/video.
        if ($percent <= 5.0 && $this->lastPercent >= 95.0) {
            $this->stream++;
        }

        $this->lastPercent = $percent;

        [$start, $range, $label] = match ($this->stream) {
            0 => [0.0, 45.0, 'Downloading media'],
            1 => [45.0, 50.0, 'Downloading additional stream'],
            default => [95.0, 4.0, 'Downloading additional stream'],
        };

        return JobProgressUpdate::ofPercent(
            percent: min(99.0, $start + ($percent / 100 * $range)),
            label: sprintf('%s: %d%%', $label, (int) $percent),
            etaSeconds: $progress->etaSeconds,
            rate: $progress->speedBytesPerSecond,
        );
    }
}
