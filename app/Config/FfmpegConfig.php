<?php

declare(strict_types=1);

namespace App\Config;

final readonly class FfmpegConfig
{
    public function __construct(
        public string $binary,
        public int $timeoutSeconds,
    ) {
    }
}
