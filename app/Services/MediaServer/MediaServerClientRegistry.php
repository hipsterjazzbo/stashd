<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Domain\MediaServer\Contract\MediaServerClient;
use App\Domain\MediaServer\MediaServerConnectionRecord;
use App\Domain\MediaServer\MediaServerType;

final readonly class MediaServerClientRegistry
{
    public function __construct(
        private JellyfinMediaServerClient $jellyfin,
        private PlexMediaServerClient $plex,
    ) {
    }

    public function clientFor(MediaServerConnectionRecord $connection): MediaServerClient
    {
        return match ($connection->type) {
            MediaServerType::Jellyfin => $this->jellyfin,
            MediaServerType::Plex => $this->plex,
        };
    }

    public function clientForType(MediaServerType $type): MediaServerClient
    {
        return match ($type) {
            MediaServerType::Jellyfin => $this->jellyfin,
            MediaServerType::Plex => $this->plex,
        };
    }
}
