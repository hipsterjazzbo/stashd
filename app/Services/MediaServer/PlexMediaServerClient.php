<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Domain\MediaServer\Contract\MediaServerClient;
use App\Domain\MediaServer\MediaServerConnectionRecord;
use App\Domain\MediaServer\MediaServerException;
use App\Domain\MediaServer\MediaServerHttpClient;
use App\Domain\MediaServer\MediaServerLibraryRef;
use App\Domain\MediaServer\MediaServerStatus;
use App\Domain\MediaServer\MediaServerTriggerResult;

final readonly class PlexMediaServerClient implements MediaServerClient
{
    public function __construct(
        private MediaServerHttpClient $http,
    ) {
    }

    public function testConnection(MediaServerConnectionRecord $connection, string $token): MediaServerStatus
    {
        $response = $this->http->request(
            method: 'GET',
            url: $this->url($connection, '/identity', $token),
            headers: ['Accept' => 'application/json'],
        );

        if ($response->status < 200 || $response->status >= 300) {
            return new MediaServerStatus(
                ok: false,
                message: 'Plex connection failed with HTTP ' . $response->status,
            );
        }

        return new MediaServerStatus(
            ok: true,
            message: 'Plex connection OK.',
            serverName: 'Plex',
        );
    }

    public function listLibraries(MediaServerConnectionRecord $connection, string $token): array
    {
        $response = $this->http->request(
            method: 'GET',
            url: $this->url($connection, '/library/sections', $token),
            headers: ['Accept' => 'application/json'],
        );

        if ($response->status < 200 || $response->status >= 300) {
            throw MediaServerException::withCode(
                'media_server_list_libraries_failed',
                'Plex library list failed with HTTP ' . $response->status,
            );
        }

        $json = json_decode($response->body, true);
        $directories = is_array($json) ? ($json['MediaContainer']['Directory'] ?? []) : [];

        if (is_array($directories) && isset($directories['key'])) {
            $directories = [$directories];
        }

        $libraries = [];

        foreach ($directories as $item) {
            if (! is_array($item)) {
                continue;
            }

            $libraries[] = new MediaServerLibraryRef(
                id: (string) ($item['key'] ?? ''),
                name: (string) ($item['title'] ?? 'Library'),
                type: isset($item['type']) ? (string) $item['type'] : null,
            );
        }

        return $libraries;
    }

    public function triggerScan(
        MediaServerConnectionRecord $connection,
        string $token,
        MediaServerLibraryRef $library,
        ?string $path = null,
    ): MediaServerTriggerResult {
        unset($path);

        $response = $this->http->request(
            method: 'GET',
            url: $this->url($connection, '/library/sections/' . rawurlencode($library->id) . '/refresh', $token),
            headers: ['Accept' => 'application/json'],
        );

        if ($response->status >= 200 && $response->status < 300) {
            return new MediaServerTriggerResult(
                ok: true,
                message: 'Plex library refresh triggered for ' . $library->name . '.',
                httpStatus: $response->status,
            );
        }

        return new MediaServerTriggerResult(
            ok: false,
            message: 'Plex library refresh failed with HTTP ' . $response->status,
            httpStatus: $response->status,
        );
    }

    private function url(MediaServerConnectionRecord $connection, string $path, string $token): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';

        return rtrim($connection->baseUri, '/') . $path . $separator . 'X-Plex-Token=' . rawurlencode($token);
    }
}
