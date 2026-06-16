<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Stash\StashItemRecord;
use App\Domain\Stash\StashItemState;
use App\Domain\Support\PrefixedUlid;
use App\Domain\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

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
    ): StashItemRecord {
        $id = $this->ids->generate('item')->toString();
        $record = new StashItemRecord(
            stashId: $stashId->toString(),
            mediaItemId: $mediaItemId->toString(),
            state: $state,
            stashInputId: $stashInputId?->toString(),
            position: $position,
            firstSeenAt: RecordTimestamps::now(),
            lastSeenAt: RecordTimestamps::now(),
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

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
}
