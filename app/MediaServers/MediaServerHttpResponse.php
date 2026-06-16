<?php

declare(strict_types=1);

namespace App\MediaServers;

final readonly class MediaServerHttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }
}
