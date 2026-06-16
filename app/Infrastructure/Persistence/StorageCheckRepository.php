<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Storage\StorageCheckRecord;
use App\Domain\Storage\StorageCheckState;
use App\Domain\Storage\StorageCheckType;
use App\Domain\Support\PrefixedUlidGenerator;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class StorageCheckRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function record(
        string $storageLocationId,
        StorageCheckType $checkType,
        StorageCheckState $state,
        ?string $message = null,
        ?array $details = null,
    ): StorageCheckRecord {
        $id = $this->ids->generate('storagecheck')->toString();
        $record = new StorageCheckRecord(
            storageLocationId: $storageLocationId,
            checkType: $checkType,
            state: $state,
            message: $message,
            detailsJson: $details === null ? null : json_encode($details, JSON_THROW_ON_ERROR),
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(StorageCheckRecord::class)->insert($record)->execute();

        return StorageCheckRecord::findById(new PrimaryKey($id))
            ?? $record;
    }
}
