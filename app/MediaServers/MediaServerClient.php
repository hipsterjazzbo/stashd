<?php

declare(strict_types=1);

namespace App\MediaServers;

interface MediaServerClient
{
    public function testConnection(MediaServerConnectionRecord $connection, string $token): MediaServerStatus;

    /** @return list<MediaServerLibraryRef> */
    public function listLibraries(MediaServerConnectionRecord $connection, string $token): array;

    public function triggerScan(
        MediaServerConnectionRecord $connection,
        string $token,
        MediaServerLibraryRef $library,
        ?string $path = null,
    ): MediaServerTriggerResult;
}
