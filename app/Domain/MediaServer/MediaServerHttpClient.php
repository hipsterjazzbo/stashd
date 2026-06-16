<?php

declare(strict_types=1);

namespace App\Domain\MediaServer;

interface MediaServerHttpClient
{
    /** @param array<string, string> $headers */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSeconds = 15,
    ): MediaServerHttpResponse;
}
