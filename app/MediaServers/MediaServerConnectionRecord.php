<?php

declare(strict_types=1);

namespace App\MediaServers;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'media_server_connections')]
final class MediaServerConnectionRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public MediaServerType $type,
        public string $name,
        public string $baseUri,
        public MediaServerConnectionState $state,
        public ?string $tokenSecretId = null,
        public ?string $settingsJson = null,
        public ?string $lastCheckedAt = null,
        public ?string $lastError = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
