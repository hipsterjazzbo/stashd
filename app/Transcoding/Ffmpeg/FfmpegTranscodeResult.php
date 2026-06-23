<?php

declare(strict_types=1);

namespace App\Transcoding\Ffmpeg;

final readonly class FfmpegTranscodeResult
{
    public function __construct(
        public bool $successful,
        public int $exitCode,
    ) {
    }
}
