<?php

declare(strict_types=1);

namespace App\Infrastructure\MediaServer;

use App\Domain\MediaServer\MediaServerHttpClient;
use App\Domain\MediaServer\MediaServerHttpResponse;

use function Tempest\Support\str;

final readonly class FixtureMediaServerHttpClient implements MediaServerHttpClient
{
    /** @param array<string, string> $map pattern => fixture filename */
    public function __construct(
        private string $fixturesDirectory,
        private array $map = [],
    ) {
    }

    /** @param array<string, string> $headers */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSeconds = 15,
    ): MediaServerHttpResponse {
        unset($method, $headers, $body, $timeoutSeconds);

        $fixture = $this->map[$url] ?? null;

        if ($fixture === null) {
            foreach ($this->map as $pattern => $file) {
                if (str($url)->contains($pattern)) {
                    $fixture = $file;
                    break;
                }
            }
        }

        if ($fixture === null) {
            return new MediaServerHttpResponse(404, '');
        }

        if (str($fixture)->startsWith('status:')) {
            $status = (int) substr($fixture, 7);

            return new MediaServerHttpResponse($status, '');
        }

        $path = rtrim($this->fixturesDirectory, '/') . '/' . ltrim($fixture, '/');

        if (! is_file($path)) {
            return new MediaServerHttpResponse(404, '');
        }

        $responseBody = file_get_contents($path);

        return new MediaServerHttpResponse(200, is_string($responseBody) ? $responseBody : '');
    }
}
