<?php

declare(strict_types=1);

namespace App\Stashes\Api;

use App\Http\Api\ApiJson;
use App\Stashes\StashItemRecord;
use App\Support\Arrayable;
use App\Vault\MediaItemRecord;

final readonly class StashItemResource implements Arrayable
{
    public function __construct(
        private StashItemRecord $item,
        private ?MediaItemRecord $mediaItem = null,
        private ?int $totalAssetSizeBytes = null,
    ) {
    }

    public static function fromRecord(
        StashItemRecord $item,
        ?MediaItemRecord $mediaItem = null,
        ?int $totalAssetSizeBytes = null,
    ): self {
        return new self($item, $mediaItem, $totalAssetSizeBytes);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->item->id,
            'stashId' => $this->item->stashId,
            'mediaItemId' => $this->item->mediaItemId,
            'stashInputId' => $this->item->stashInputId,
            'state' => $this->item->state->value,
            'position' => $this->item->position,
            'seasonNumber' => $this->item->seasonNumber,
            'episodeNumber' => $this->item->episodeNumber,
            'seasonTitle' => $this->item->seasonTitle,
            'displayTitle' => $this->item->displayTitle,
            'displayDescription' => $this->item->displayDescription,
            'firstSeenAt' => $this->item->firstSeenAt,
            'lastSeenAt' => $this->item->lastSeenAt,
            'removedAt' => $this->item->removedAt,
            'removedReason' => $this->item->removedReason,
            'ignoredReason' => $this->item->ignoredReason,
            'createdAt' => $this->item->createdAt,
            'updatedAt' => $this->item->updatedAt,
            'mediaItem' => $this->mediaItem === null ? null : [
                'title' => $this->mediaItem->title,
                'state' => $this->mediaItem->state->value,
                'thumbnailUri' => $this->mediaItem->thumbnailUri,
                'durationSeconds' => $this->mediaItem->durationSeconds,
                'contentType' => $this->mediaItem->contentType,
            ],
            'totalAssetSizeBytes' => $this->totalAssetSizeBytes,
        ]);
    }
}
