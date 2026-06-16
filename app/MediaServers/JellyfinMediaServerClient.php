<?php

declare(strict_types=1);

namespace App\MediaServers;

final readonly class JellyfinMediaServerClient implements MediaServerClient
{
    public function __construct(
        private MediaServerHttpClient $http,
    ) {
    }

    public function testConnection(MediaServerConnectionRecord $connection, string $token): MediaServerStatus
    {
        $response = $this->http->request(
            method: 'GET',
            url: $this->url($connection, '/System/Info/Public'),
            headers: $this->headers($token),
        );

        if ($response->status < 200 || $response->status >= 300) {
            return new MediaServerStatus(
                ok: false,
                message: 'Jellyfin connection failed with HTTP ' . $response->status,
            );
        }

        $json = json_decode($response->body, true);

        return new MediaServerStatus(
            ok: true,
            message: 'Jellyfin connection OK.',
            serverName: is_array($json) ? (string) ($json['ServerName'] ?? 'Jellyfin') : 'Jellyfin',
            version: is_array($json) ? (string) ($json['Version'] ?? null) : null,
        );
    }

    public function listLibraries(MediaServerConnectionRecord $connection, string $token): array
    {
        $response = $this->http->request(
            method: 'GET',
            url: $this->url($connection, '/Library/MediaFolders'),
            headers: $this->headers($token),
        );

        if ($response->status < 200 || $response->status >= 300) {
            throw MediaServerException::withCode(
                'media_server_list_libraries_failed',
                'Jellyfin library list failed with HTTP ' . $response->status,
            );
        }

        $json = json_decode($response->body, true);
        $items = is_array($json) ? ($json['Items'] ?? []) : [];
        $libraries = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $libraries[] = new MediaServerLibraryRef(
                id: (string) ($item['Id'] ?? ''),
                name: (string) ($item['Name'] ?? 'Library'),
                type: isset($item['CollectionType']) ? (string) $item['CollectionType'] : null,
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
            method: 'POST',
            url: $this->url($connection, '/Library/Refresh'),
            headers: $this->headers($token),
        );

        if ($response->status >= 200 && $response->status < 300) {
            return new MediaServerTriggerResult(
                ok: true,
                message: 'Jellyfin library refresh triggered for ' . $library->name . '.',
                httpStatus: $response->status,
            );
        }

        return new MediaServerTriggerResult(
            ok: false,
            message: 'Jellyfin library refresh failed with HTTP ' . $response->status,
            httpStatus: $response->status,
        );
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return [
            'X-Emby-Token' => $token,
            'Accept' => 'application/json',
        ];
    }

    private function url(MediaServerConnectionRecord $connection, string $path): string
    {
        return rtrim($connection->baseUri, '/') . $path;
    }
}
