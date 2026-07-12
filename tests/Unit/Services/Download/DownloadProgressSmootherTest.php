<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Download;

use App\Downloads\DownloadProgressSmoother;
use Ytdlphp\DownloadProgress;

test('download progress stays monotonic across yt-dlp stream resets', function (): void {
    $smoother = new DownloadProgressSmoother();

    $updates = array_map(
        fn (float $percent) => $smoother->update(new DownloadProgress(0, 100, $percent, null, null)),
        [0.0, 100.0, 0.0, 100.0],
    );

    expect(array_column($updates, 'percent'))->toBe([0.0, 45.0, 45.0, 95.0])
        ->and($updates[2]->label)->toStartWith('Downloading additional stream');
});
