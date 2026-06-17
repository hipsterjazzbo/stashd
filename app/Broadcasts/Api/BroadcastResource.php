<?php

declare(strict_types=1);

namespace App\Broadcasts\Api;

use App\Broadcasts\BroadcastRecord;
use App\Http\Api\ApiJson;
use App\Support\Arrayable;

final readonly class BroadcastResource implements Arrayable
{

    public function __construct(
        private BroadcastRecord $broadcast,
        private ?string $feedUrl = null,
    )
    {
    }

    public static function fromRecord(BroadcastRecord $broadcast, ?string $feedUrl = null): BroadcastResource
    {
        return new self($broadcast, $feedUrl);
    }

    public function toArray(): array
    {
        $payload = [
            'id' => (string) $this->broadcast->id,
            'stashId' => $this->broadcast->stashId,
            'type' => $this->broadcast->type->value,
            'name' => $this->broadcast->name,
            'slug' => $this->broadcast->slug,
            'state' => $this->broadcast->state->value,
            'settings' => $this->decodeJson($this->broadcast->settingsJson),
            'lastPlannedAt' => $this->broadcast->lastPlannedAt,
            'lastBuiltAt' => $this->broadcast->lastBuiltAt,
            'lastVerifiedAt' => $this->broadcast->lastVerifiedAt,
            'lastError' => $this->broadcast->lastError,
            'createdAt' => $this->broadcast->createdAt,
            'updatedAt' => $this->broadcast->updatedAt,
        ];

        if ($this->feedUrl !== null) {
            $payload['feedUrl'] = $this->feedUrl;
            $payload['tokenPreview'] = $this->broadcast->tokenPreview;
        }

        return ApiJson::encode($payload);
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? ApiJson::encode($decoded) : null;
    }
}
