<?php

declare(strict_types=1);

namespace App\Vault;

use App\Support\DurationSeconds;
use App\Support\PrefixedUlidGenerator;
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
        MediaItemId $mediaItemId,
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
            mediaItemId: $mediaItemId,
            path: $path,
            relativePath: $relativePath,
            mimeType: $mimeType,
            container: $container,
            sizeBytes: $sizeBytes,
            checksum: $checksum,
            durationSeconds: DurationSeconds::toDuration($durationSeconds),
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(AssetRecord::class)->insert($record)->execute();

        return $record;
    }

    public function find(AssetId $id): ?AssetRecord
    {
        return AssetRecord::findById($id->toPrimaryKey());
    }

    public function save(AssetRecord $record): AssetRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    public function findByMediaItemAndRole(MediaItemId $mediaItemId, AssetRole $role): ?AssetRecord
    {
        return AssetRecord::select()
            ->where('mediaItemId = ? AND role = ?', $mediaItemId->toString(), $role)
            ->first();
    }

    /** @return list<AssetRecord> */
    public function listForMediaItem(MediaItemId $mediaItemId): array
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

    /**
     * Total on-disk size across every asset for each of the given media
     * items, in one query — avoids an N+1 per stash item on the items list.
     *
     * @param list<string> $mediaItemIds
     *
     * @return array<string, int> keyed by media item id
     */
    public function totalSizeBytesByMediaItem(array $mediaItemIds): array
    {
        if ($mediaItemIds === []) {
            return [];
        }

        $totals = [];

        foreach (AssetRecord::select()->whereIn('mediaItemId', $mediaItemIds)->all() as $asset) {
            $key = (string) $asset->mediaItemId;
            $totals[$key] = ($totals[$key] ?? 0) + ($asset->sizeBytes ?? 0);
        }

        return $totals;
    }
}
