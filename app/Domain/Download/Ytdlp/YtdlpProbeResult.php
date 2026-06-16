<?php

declare(strict_types=1);

namespace App\Domain\Download\Ytdlp;

final readonly class YtdlpProbeResult
{
    public function __construct(
        public bool $available,
        public string $binary,
        public ?string $version = null,
        public ?string $message = null,
    ) {
    }
}
