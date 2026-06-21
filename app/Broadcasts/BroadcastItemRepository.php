<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class BroadcastItemRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $broadcastId,
        PrefixedUlid $stashItemId,
        PrefixedUlid $mediaItemId,
        BroadcastItemState $state = BroadcastItemState::Pending,
    ): BroadcastItemRecord {
        $id = $this->ids->generate('bitem')->toString();
        $record = new BroadcastItemRecord(
            broadcastId: $broadcastId->toString(),
            stashItemId: $stashItemId->toString(),
            mediaItemId: $mediaItemId->toString(),
            state: $state,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(BroadcastItemRecord::class)->insert($record)->execute();

        return BroadcastItemRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist broadcast item record.');
    }

    public function find(PrefixedUlid $id): ?BroadcastItemRecord
    {
        return BroadcastItemRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(BroadcastItemRecord $record): BroadcastItemRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    /** @return list<BroadcastItemRecord> */
    public function listForBroadcast(PrefixedUlid $broadcastId): array
    {
        return BroadcastItemRecord::select()
            ->where('broadcastId = ?', $broadcastId->toString())
            ->orderBy('createdAt', \Tempest\Database\Direction::ASC)
            ->all();
    }

    public function findByBroadcastAndStashItem(
        PrefixedUlid $broadcastId,
        PrefixedUlid $stashItemId,
    ): ?BroadcastItemRecord {
        return BroadcastItemRecord::select()
            ->where('broadcastId = ? AND stashItemId = ?', $broadcastId->toString(), $stashItemId->toString())
            ->first();
    }
}
