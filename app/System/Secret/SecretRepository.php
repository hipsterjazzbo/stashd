<?php

declare(strict_types=1);

namespace App\System\Secret;

use App\Support\PrefixedUlidGenerator;
use App\Support\RecordTimestamps;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class SecretRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function findByKey(string $key): ?SecretRecord
    {
        return SecretRecord::select()
            ->where('key = ? AND revokedAt IS NULL', $key)
            ->first();
    }

    public function find(\App\Support\PrefixedUlid $id): ?SecretRecord
    {
        return SecretRecord::findById(new PrimaryKey($id->toString()));
    }

    public function create(
        string $key,
        SecretType $type,
        string $encryptedValue,
        string $nonce,
        ?array $metadata = null,
    ): SecretRecord {
        $id = $this->ids->generate('secret')->toString();
        $record = new SecretRecord(
            key: $key,
            type: $type,
            encryptedValue: $encryptedValue,
            nonce: $nonce,
            metadataJson: $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(SecretRecord::class)->insert($record)->execute();

        return SecretRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist secret record.');
    }

    public function save(SecretRecord $record): SecretRecord
    {
        $record->updatedAt = RecordTimestamps::now();
        $record->save();

        return $record;
    }
}
