<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Support\PrefixedUlidGenerator;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class StashInputRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        StashId $stashId,
        string $providerKey,
        StashInputType $inputType,
        string $sourceUri,
        string $providerInputId,
        ?string $title = null,
        ?SyncMode $syncMode = null,
        ?StashInputOptions $options = null,
    ): StashInputRecord {
        $id = $this->ids->generate('input')->toString();
        $record = new StashInputRecord(
            stashId: $stashId,
            providerKey: $providerKey,
            inputType: $inputType,
            sourceUri: $sourceUri,
            providerInputId: $providerInputId,
            state: StashInputState::Ready,
            title: $title,
            syncMode: $syncMode,
            options: $options,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(StashInputRecord::class)->insert($record)->execute();

        return $record;
    }

    public function find(StashInputId $id): ?StashInputRecord
    {
        return StashInputRecord::findById($id->toPrimaryKey());
    }

    public function save(StashInputRecord $record): StashInputRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    /**
     * Only affects future discovery/sync passes for this input -- items
     * already committed (and their ignoredReason) are not retroactively
     * re-filtered.
     */
    public function updateOptions(StashInputRecord $record, ?StashInputOptions $options): StashInputRecord
    {
        $record->options = $options;

        return $this->save($record);
    }

    /** @return list<StashInputRecord> */
    public function listDueForAutomaticSync(DateTime $now): array
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
    public function listForStash(StashId $stashId): array
    {
        return StashInputRecord::select()
            ->where('stashId = ?', $stashId->toString())
            ->orderBy('createdAt', Direction::ASC)
            ->all();
    }
}
