<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Broadcast\BroadcastTriggerRunRecord;
use App\Domain\Broadcast\BroadcastTriggerRunState;
use App\Domain\Support\PrefixedUlid;
use App\Domain\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class BroadcastTriggerRunRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $triggerId,
        BroadcastTriggerRunState $state = BroadcastTriggerRunState::Pending,
        ?string $reason = null,
    ): BroadcastTriggerRunRecord {
        $id = $this->ids->generate('btrun')->toString();
        $record = new BroadcastTriggerRunRecord(
            triggerId: $triggerId->toString(),
            state: $state,
            reason: $reason,
            startedAt: RecordTimestamps::now(),
        );
        $record->id = new PrimaryKey($id);
        $record->createdAt = RecordTimestamps::now();

        query(BroadcastTriggerRunRecord::class)->insert($record)->execute();

        return BroadcastTriggerRunRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist broadcast trigger run.');
    }

    public function save(BroadcastTriggerRunRecord $record): BroadcastTriggerRunRecord
    {
        $record->save();

        return $record;
    }
}
