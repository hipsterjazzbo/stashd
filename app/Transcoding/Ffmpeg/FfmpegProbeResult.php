<?php

declare(strict_types=1);

namespace App\Transcoding\Ffmpeg;

final readonly class FfmpegProbeResult
{
    public function __construct(
        public bool $available,
        public string $binary,
        public ?string $version = null,
        public ?string $message = null,
    ) {
    }
}
