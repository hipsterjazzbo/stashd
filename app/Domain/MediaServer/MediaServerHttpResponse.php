<?php

declare(strict_types=1);

namespace App\Domain\MediaServer;

final readonly class MediaServerHttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }
}
