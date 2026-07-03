<?php

declare(strict_types=1);

namespace App\MediaServers\Http;

use App\MediaServers\MediaServerHttpClient;
use App\MediaServers\MediaServerHttpResponse;
use App\Support\Http\CurlClient;
use InvalidArgumentException;

final class CurlMediaServerHttpClient implements MediaServerHttpClient
{
    /** @param array<string, string> $headers */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSeconds = 15,
    ): MediaServerHttpResponse {
        if ($method === '') {
            throw new InvalidArgumentException('HTTP method must not be empty.');
        }

        $response = CurlClient::send($method, $url, $headers, $body, $timeoutSeconds);

        if ($response === null) {
            return new MediaServerHttpResponse(0, '');
        }

        return new MediaServerHttpResponse(
            status: $response['status'],
            body: $response['body'],
        );
    }
}
