<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Support\PrefixedUlidGenerator;
use App\Vault\MediaItemId;
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
        StashId $stashId,
        MediaItemId $mediaItemId,
        ?StashInputId $stashInputId = null,
        StashItemState $state = StashItemState::Active,
        ?int $position = null,
        ?string $ignoredReason = null,
    ): StashItemRecord {
        $id = $this->ids->generate('item')->toString();
        $record = new StashItemRecord(
            stashId: $stashId,
            mediaItemId: $mediaItemId,
            state: $state,
            stashInputId: $stashInputId,
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

    public function find(StashItemId $id): ?StashItemRecord
    {
        return StashItemRecord::findById(new PrimaryKey($id->toString()));
    }

    public function findByStashAndMediaItem(StashId $stashId, MediaItemId $mediaItemId): ?StashItemRecord
    {
        return StashItemRecord::select()
            ->where('stashId = ? AND mediaItemId = ?', $stashId->toString(), $mediaItemId->toString())
            ->first();
    }

    /** @return list<StashItemRecord> */
    public function listForStash(StashId $stashId): array
    {
        return StashItemRecord::select()
            ->where('stashId = ?', $stashId->toString())
            ->orderBy('position', Direction::ASC)
            ->all();
    }

    /** @return list<StashItemRecord> */
    public function listForMediaItem(MediaItemId $mediaItemId): array
    {
        return StashItemRecord::select()
            ->where('mediaItemId = ?', $mediaItemId->toString())
            ->all();
    }

    /**
     * @param list<string> $mediaItemIds
     * @return list<StashItemRecord>
     */
    public function listForMediaItemsExcludingStash(array $mediaItemIds, StashId $excludingStashId): array
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
