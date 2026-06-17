<?php

declare(strict_types=1);

namespace App\Vault\Api;

use App\Http\Api\ApiJson;
use App\Support\Arrayable;
use App\Vault\MediaItemRecord;

final readonly class MediaItemResource implements Arrayable
{
    public function __construct(
        private MediaItemRecord $item,
    ) {
    }

    public static function fromRecord(MediaItemRecord $item): self
    {
        return new self($item);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->item->id,
            'providerKey' => $this->item->providerKey,
            'providerItemId' => $this->item->providerItemId,
            'canonicalUri' => $this->item->canonicalUri,
            'title' => $this->item->title,
            'state' => $this->item->state->value,
            'durationSeconds' => $this->item->durationSeconds,
            'publishedAt' => $this->item->publishedAt,
            'thumbnailUri' => $this->item->thumbnailUri,
            'createdAt' => $this->item->createdAt,
            'updatedAt' => $this->item->updatedAt,
        ]);
    }
}
