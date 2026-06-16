<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\ApiTokenRecord;
use App\Domain\Support\PrefixedUlid;
use App\Domain\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class ApiTokenRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $userId,
        string $name,
        string $tokenHash,
        string $tokenPreview,
        ?array $scopes = null,
        ?string $expiresAt = null,
    ): ApiTokenRecord {
        $id = $this->ids->generate('token')->toString();
        $now = RecordTimestamps::now();
        $record = new ApiTokenRecord(
            userId: $userId->toString(),
            name: $name,
            tokenHash: $tokenHash,
            tokenPreview: $tokenPreview,
            scopesJson: $scopes === null ? null : json_encode($scopes, JSON_THROW_ON_ERROR),
            expiresAt: $expiresAt,
            createdAt: $now,
        );
        $record->id = new PrimaryKey($id);

        query(ApiTokenRecord::class)->insert($record)->execute();

        return ApiTokenRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist API token record.');
    }

    public function findByHash(string $tokenHash): ?ApiTokenRecord
    {
        return ApiTokenRecord::select()
            ->where('tokenHash = ? AND revokedAt IS NULL', $tokenHash)
            ->first();
    }

    /** @return list<ApiTokenRecord> */
    public function listForUser(PrefixedUlid $userId): array
    {
        return ApiTokenRecord::select()
            ->where('userId = ? AND revokedAt IS NULL', $userId->toString())
            ->orderBy('createdAt', \Tempest\Database\Direction::DESC)
            ->all();
    }

    public function revoke(PrefixedUlid $tokenId): void
    {
        $record = ApiTokenRecord::findById(new PrimaryKey($tokenId->toString()));

        if ($record === null || $record->revokedAt !== null) {
            return;
        }

        $record->revokedAt = RecordTimestamps::now();
        $record->save();
    }
}
