<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use App\Support\RecordTimestamps;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class BroadcastTriggerRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $broadcastId,
        BroadcastTriggerType $type,
        bool $enabled = true,
        BroadcastTriggerState $state = BroadcastTriggerState::Ready,
        ?array $settings = null,
    ): BroadcastTriggerRecord {
        $id = $this->ids->generate('btrigger')->toString();
        $record = new BroadcastTriggerRecord(
            broadcastId: $broadcastId->toString(),
            type: $type,
            enabled: $enabled,
            state: $state,
            settingsJson: $settings === null ? null : json_encode($settings, JSON_THROW_ON_ERROR),
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(BroadcastTriggerRecord::class)->insert($record)->execute();

        return BroadcastTriggerRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist broadcast trigger.');
    }

    public function save(BroadcastTriggerRecord $record): BroadcastTriggerRecord
    {
        $record->updatedAt = RecordTimestamps::now();
        $record->save();

        return $record;
    }

    /** @return list<BroadcastTriggerRecord> */
    public function listForBroadcast(PrefixedUlid $broadcastId): array
    {
        return BroadcastTriggerRecord::select()
            ->where('broadcastId = ?', $broadcastId->toString())
            ->all();
    }

    public function findEnabledScanTrigger(PrefixedUlid $broadcastId, BroadcastTriggerType $type): ?BroadcastTriggerRecord
    {
        return BroadcastTriggerRecord::select()
            ->where('broadcastId = ? AND type = ? AND enabled = 1', $broadcastId->toString(), $type)
            ->first();
    }
}
