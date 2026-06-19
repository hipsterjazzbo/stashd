<?php

declare(strict_types=1);

namespace App\Stashes\Api;

use App\Http\Api\ApiJson;
use App\Stashes\StashItemRecord;
use App\Support\Arrayable;

final readonly class StashItemResource implements Arrayable
{
    public function __construct(
        private StashItemRecord $item,
    ) {
    }

    public static function fromRecord(StashItemRecord $item): self
    {
        return new self($item);
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
        ]);
    }
}
