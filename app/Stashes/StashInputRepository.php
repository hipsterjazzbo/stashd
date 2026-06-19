<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use App\Support\RecordTimestamps;
use InvalidArgumentException;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class StashInputRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $stashId,
        string $providerKey,
        StashInputType $inputType,
        string $sourceUri,
        string $providerInputId,
        ?string $title = null,
        ?SyncMode $syncMode = null,
    ): StashInputRecord {
        $id = $this->ids->generate('input')->toString();
        $record = new StashInputRecord(
            stashId: $stashId->toString(),
            providerKey: $providerKey,
            inputType: $inputType,
            sourceUri: $sourceUri,
            providerInputId: $providerInputId,
            state: StashInputState::Ready,
            title: $title,
            syncMode: $syncMode,
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(StashInputRecord::class)->insert($record)->execute();

        return StashInputRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist stash input record.');
    }

    public function find(PrefixedUlid $id): ?StashInputRecord
    {
        return StashInputRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(StashInputRecord $record): StashInputRecord
    {
        $record->updatedAt = RecordTimestamps::now();
        $record->save();

        return $record;
    }

    /** @return list<StashInputRecord> */
    public function listDueForAutomaticSync(string $now): array
    {
        return StashInputRecord::select()
            ->where(
                'state = ? AND syncMode = ? AND (nextCheckAt IS NULL OR nextCheckAt <= ?)',
                StashInputState::Ready,
                SyncMode::Automatic,
                $now,
            )
            ->orderBy('nextCheckAt', Direction::ASC)
            ->all();
    }

    /** @return list<StashInputRecord> */
    public function listForStash(PrefixedUlid $stashId): array
    {
        return StashInputRecord::select()
            ->where('stashId = ?', $stashId->toString())
            ->orderBy('createdAt', Direction::ASC)
            ->all();
    }
}
