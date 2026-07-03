<?php

declare(strict_types=1);

namespace App\System\Secret;

use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

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
            ->include('encryptedValue', 'nonce', 'metadata')
            ->first();
    }

    public function find(\App\Support\PrefixedUlid $id): ?SecretRecord
    {
        return SecretRecord::findById(new PrimaryKey($id->toString()));
    }

    /** @param array<string, mixed>|null $metadata */
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
            metadata: $metadata,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(SecretRecord::class)->insert($record)->execute();

        return SecretRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist secret record.');
    }

    public function save(SecretRecord $record): SecretRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }
}
