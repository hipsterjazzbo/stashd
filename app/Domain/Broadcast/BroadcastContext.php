<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

use App\Domain\Media\AssetRecord;
use App\Domain\Media\MediaItemRecord;
use App\Domain\Stash\StashItemRecord;
use App\Domain\Stash\StashRecord;

/** Runtime context for broadcast lifecycle operations. */
final readonly class BroadcastContext
{
    /**
     * @param list<StashItemRecord> $stashItems
     * @param array<string, MediaItemRecord> $mediaItems keyed by media item id
     * @param array<string, AssetRecord|null> $vaultOriginals keyed by media item id
     */
    public function __construct(
        public BroadcastRecord $broadcast,
        public StashRecord $stash,
        public array $stashItems,
        public array $mediaItems,
        public array $vaultOriginals,
    ) {
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        if ($this->broadcast->settingsJson === null) {
            return [];
        }

        $decoded = json_decode($this->broadcast->settingsJson, true);

        return is_array($decoded) ? $decoded : [];
    }
}
