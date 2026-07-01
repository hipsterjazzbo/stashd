<?php

declare(strict_types=1);

namespace App\Broadcasts;

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
        string|\Stringable $broadcastId,
        string|\Stringable $stashItemId,
        string|\Stringable $mediaItemId,
        BroadcastItemState $state = BroadcastItemState::Pending,
    ): BroadcastItemRecord {
        $id = $this->ids->generate('bitem')->toString();
        $record = new BroadcastItemRecord(
            broadcastId: (string) $broadcastId,
            stashItemId: (string) $stashItemId,
            mediaItemId: (string) $mediaItemId,
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

    public function find(string|\Stringable $id): ?BroadcastItemRecord
    {
        return BroadcastItemRecord::findById(new PrimaryKey((string) $id));
    }

    public function save(BroadcastItemRecord $record): BroadcastItemRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    /** @return list<BroadcastItemRecord> */
    public function listForBroadcast(string|\Stringable $broadcastId): array
    {
        return BroadcastItemRecord::select()
            ->where('broadcastId = ?', (string) $broadcastId)
            ->orderBy('createdAt', \Tempest\Database\Direction::ASC)
            ->all();
    }

    public function findByBroadcastAndStashItem(
        string|\Stringable $broadcastId,
        string|\Stringable $stashItemId,
    ): ?BroadcastItemRecord {
        return BroadcastItemRecord::select()
            ->where('broadcastId = ? AND stashItemId = ?', (string) $broadcastId, (string) $stashItemId)
            ->first();
    }

    /** @return list<BroadcastItemRecord> */
    public function listForMediaItem(string|\Stringable $mediaItemId): array
    {
        return BroadcastItemRecord::select()
            ->where('mediaItemId = ?', (string) $mediaItemId)
            ->all();
    }
}
