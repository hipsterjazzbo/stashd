<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Storage\StorageLocationKey;
use App\Domain\Storage\StorageLocationRecord;
use App\Domain\Storage\StorageLocationState;
use App\Domain\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class StorageLocationRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function upsert(
        StorageLocationKey $key,
        string $role,
        string $label,
        string $path,
        StorageLocationState $state,
        bool $readable,
        bool $writable,
        ?int $freeBytes,
        ?int $totalBytes,
        ?string $filesystemId,
        bool $supportsHardlinks,
        bool $supportsSymlinks,
        ?string $lastError,
    ): StorageLocationRecord {
        $existing = StorageLocationRecord::select()
            ->where('key = ?', $key)
            ->first();

        if ($existing !== null) {
            $existing->role = $role;
            $existing->label = $label;
            $existing->path = $path;
            $existing->state = $state;
            $existing->readable = $readable;
            $existing->writable = $writable;
            $existing->freeBytes = $freeBytes;
            $existing->totalBytes = $totalBytes;
            $existing->filesystemId = $filesystemId;
            $existing->supportsHardlinks = $supportsHardlinks;
            $existing->supportsSymlinks = $supportsSymlinks;
            $existing->lastCheckedAt = gmdate('Y-m-d H:i:s');
            $existing->lastError = $lastError;
            $existing->save();

            return $existing;
        }

        $id = $this->ids->generate('storage')->toString();
        $record = new StorageLocationRecord(
            key: $key,
            role: $role,
            label: $label,
            path: $path,
            state: $state,
            readable: $readable,
            writable: $writable,
            freeBytes: $freeBytes,
            totalBytes: $totalBytes,
            filesystemId: $filesystemId,
            supportsHardlinks: $supportsHardlinks,
            supportsSymlinks: $supportsSymlinks,
            lastCheckedAt: gmdate('Y-m-d H:i:s'),
            lastError: $lastError,
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(StorageLocationRecord::class)->insert($record)->execute();

        return StorageLocationRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist storage location.');
    }

    /** @return list<StorageLocationRecord> */
    public function all(): array
    {
        return StorageLocationRecord::select()->all();
    }

    public function findByKey(StorageLocationKey $key): ?StorageLocationRecord
    {
        return StorageLocationRecord::select()
            ->where('key = ?', $key)
            ->first();
    }
}
