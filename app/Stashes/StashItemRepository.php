<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class StashItemRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $stashId,
        PrefixedUlid $mediaItemId,
        ?PrefixedUlid $stashInputId = null,
        StashItemState $state = StashItemState::Active,
        ?int $position = null,
        ?string $ignoredReason = null,
    ): StashItemRecord {
        $id = $this->ids->generate('item')->toString();
        $record = new StashItemRecord(
            stashId: $stashId->toString(),
            mediaItemId: $mediaItemId->toString(),
            state: $state,
            stashInputId: $stashInputId?->toString(),
            position: $position,
            ignoredReason: $ignoredReason,
            firstSeenAt: DateTime::now(Timezone::UTC),
            lastSeenAt: DateTime::now(Timezone::UTC),
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(StashItemRecord::class)->insert($record)->execute();

        return StashItemRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist stash item record.');
    }

    public function find(PrefixedUlid $id): ?StashItemRecord
    {
        return StashItemRecord::findById(new PrimaryKey($id->toString()));
    }

    public function findByStashAndMediaItem(PrefixedUlid $stashId, PrefixedUlid $mediaItemId): ?StashItemRecord
    {
        return StashItemRecord::select()
            ->where('stashId = ? AND mediaItemId = ?', $stashId->toString(), $mediaItemId->toString())
            ->first();
    }

    /** @return list<StashItemRecord> */
    public function listForStash(PrefixedUlid $stashId): array
    {
        return StashItemRecord::select()
            ->where('stashId = ?', $stashId->toString())
            ->orderBy('position', Direction::ASC)
            ->all();
    }

    /** @return list<StashItemRecord> */
    public function listForMediaItem(PrefixedUlid $mediaItemId): array
    {
        return StashItemRecord::select()
            ->where('mediaItemId = ?', $mediaItemId->toString())
            ->all();
    }

    /**
     * @param list<string> $mediaItemIds
     * @return list<StashItemRecord>
     */
    public function listForMediaItemsExcludingStash(array $mediaItemIds, PrefixedUlid $excludingStashId): array
    {
        if ($mediaItemIds === []) {
            return [];
        }

        return StashItemRecord::select()
            ->whereIn('mediaItemId', $mediaItemIds)
            ->where('stashId != ?', $excludingStashId->toString())
            ->all();
    }
}
