<?php

declare(strict_types=1);

namespace App\Vault\Api;

use App\Http\Api\ApiJson;
use App\Support\Arrayable;
use App\Vault\AssetRecord;

final readonly class AssetResource implements Arrayable
{
    public function __construct(
        private AssetRecord $asset,
    ) {
    }

    public static function fromRecord(AssetRecord $asset): self
    {
        return new self($asset);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->asset->id,
            'mediaItemId' => $this->asset->mediaItemId,
            'role' => $this->asset->role->value,
            'kind' => $this->asset->kind->value,
            'state' => $this->asset->state->value,
            'path' => $this->asset->path,
            'relativePath' => $this->asset->relativePath,
            'mimeType' => $this->asset->mimeType,
            'container' => $this->asset->container,
            'sizeBytes' => $this->asset->sizeBytes,
            'checksum' => $this->asset->checksum,
            'durationSeconds' => $this->asset->durationSeconds,
            'lastVerifiedAt' => $this->asset->lastVerifiedAt,
            'missingAt' => $this->asset->missingAt,
            'missingReason' => $this->asset->missingReason,
            'createdAt' => $this->asset->createdAt,
            'updatedAt' => $this->asset->updatedAt,
        ]);
    }
}
