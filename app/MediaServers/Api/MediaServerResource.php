<?php

declare(strict_types=1);

namespace App\MediaServers\Api;

use App\Http\Api\ApiJson;
use App\MediaServers\MediaServerConnectionRecord;
use App\Support\Arrayable;

final readonly class MediaServerResource implements Arrayable
{
    public function __construct(
        private MediaServerConnectionRecord $connection,
    ) {
    }

    public static function fromRecord(MediaServerConnectionRecord $connection): self
    {
        return new self($connection);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->connection->id,
            'type' => $this->connection->type->value,
            'name' => $this->connection->name,
            'baseUri' => $this->connection->baseUri,
            'state' => $this->connection->state->value,
            'settings' => $this->connection->settingsJson?->toArray(),
            'lastCheckedAt' => $this->connection->lastCheckedAt,
            'lastError' => $this->connection->lastError,
            'createdAt' => $this->connection->createdAt,
            'updatedAt' => $this->connection->updatedAt,
        ]);
    }

}
