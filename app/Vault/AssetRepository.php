<?php

declare(strict_types=1);

namespace App\Vault;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class AssetRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $mediaItemId,
        AssetRole $role,
        AssetKind $kind,
        AssetState $state = AssetState::Pending,
        ?string $path = null,
        ?string $relativePath = null,
        ?string $mimeType = null,
        ?string $container = null,
        ?int $sizeBytes = null,
        ?string $checksum = null,
        ?int $durationSeconds = null,
    ): AssetRecord {
        $id = $this->ids->generate('asset')->toString();
        $record = new AssetRecord(
            role: $role,
            kind: $kind,
            state: $state,
            mediaItemId: $mediaItemId->toString(),
            path: $path,
            relativePath: $relativePath,
            mimeType: $mimeType,
            container: $container,
            sizeBytes: $sizeBytes,
            checksum: $checksum,
            durationSeconds: $durationSeconds,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(AssetRecord::class)->insert($record)->execute();

        return AssetRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist asset record.');
    }

    public function find(PrefixedUlid $id): ?AssetRecord
    {
        return AssetRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(AssetRecord $record): AssetRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    public function findByMediaItemAndRole(PrefixedUlid $mediaItemId, AssetRole $role): ?AssetRecord
    {
        return AssetRecord::select()
            ->where('mediaItemId = ? AND role = ?', $mediaItemId->toString(), $role)
            ->first();
    }

    /** @return list<AssetRecord> */
    public function listForMediaItem(PrefixedUlid $mediaItemId): array
    {
        return AssetRecord::select()
            ->where('mediaItemId = ?', $mediaItemId->toString())
            ->all();
    }

    /** @return list<AssetRecord> */
    public function listReadyVaultAssets(): array
    {
        return AssetRecord::select()
            ->where('state = ? AND path IS NOT NULL', AssetState::Ready)
            ->all();
    }
}
