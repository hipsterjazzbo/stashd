<?php

declare(strict_types=1);

namespace App\Transcoding\Ffmpeg;

final readonly class FfmpegProgress
{
    public function __construct(
        public float $currentSeconds,
        public ?int $totalSeconds,
        public float $percent,
        public ?int $etaSeconds,
    ) {
    }
}
