<?php

declare(strict_types=1);

namespace App\Broadcasts\Api;

use App\Broadcasts\BroadcastItemRecord;
use App\Http\Api\ApiJson;
use App\Support\Arrayable;

final readonly class BroadcastItemResource implements Arrayable
{
    public function __construct(
        private BroadcastItemRecord $item,
    ) {
    }

    public static function fromRecord(BroadcastItemRecord $item): self
    {
        return new self($item);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->item->id,
            'broadcastId' => (string) $this->item->broadcastId,
            'stashItemId' => (string) $this->item->stashItemId,
            'mediaItemId' => (string) $this->item->mediaItemId,
            'state' => $this->item->state->value,
            'tokenPreview' => $this->item->tokenPreview,
            'publishedPath' => $this->item->publishedPath,
            'publishedUri' => $this->item->publishedUri,
            'lastPublishedAt' => $this->item->lastPublishedAt,
            'lastVerifiedAt' => $this->item->lastVerifiedAt,
            'lastError' => $this->item->lastError,
            'createdAt' => $this->item->createdAt,
            'updatedAt' => $this->item->updatedAt,
        ]);
    }
}
