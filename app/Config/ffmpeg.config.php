<?php

declare(strict_types=1);

use App\Config\FfmpegConfig;

use function Tempest\env;

return new FfmpegConfig(
    binary: env('STASHD_FFMPEG_BINARY', 'ffmpeg'),
    timeoutSeconds: (int) env('STASHD_FFMPEG_TIMEOUT', '1800'),
);
