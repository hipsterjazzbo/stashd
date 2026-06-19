<?php

declare(strict_types=1);

namespace App\Vault;

use App\Providers\StashdUri;
use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use App\Support\RecordTimestamps;
use InvalidArgumentException;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;

final class MediaItemRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        string $providerKey,
        string $providerItemId,
        StashdUri|string $canonicalUri,
        string $title,
        MediaItemState $state = MediaItemState::Discovered,
        ?string $description = null,
        ?int $durationSeconds = null,
        ?DateTime $publishedAt = null,
        StashdUri|string|null $thumbnailUri = null,
    ): MediaItemRecord {
        $id = $this->ids->generate('media')->toString();
        $record = new MediaItemRecord(
            providerKey: $providerKey,
            providerItemId: $providerItemId,
            canonicalUri: $canonicalUri instanceof StashdUri ? $canonicalUri->toString() : $canonicalUri,
            title: $title,
            state: $state,
            upstreamState: UpstreamState::Available,
            description: $description,
            durationSeconds: $durationSeconds,
            publishedAt: $publishedAt?->toRfc3339(useZ: true),
            thumbnailUri: $thumbnailUri instanceof StashdUri ? $thumbnailUri->toString() : $thumbnailUri,
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(MediaItemRecord::class)->insert($record)->execute();

        return MediaItemRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist media item record.');
    }

    public function find(PrefixedUlid $id): ?MediaItemRecord
    {
        return MediaItemRecord::findById(new PrimaryKey($id->toString()));
    }

    public function findByProviderIdentity(string $providerKey, string $providerItemId): ?MediaItemRecord
    {
        return MediaItemRecord::select()
            ->where('providerKey = ? AND providerItemId = ?', $providerKey, $providerItemId)
            ->first();
    }

    public function save(MediaItemRecord $record): MediaItemRecord
    {
        $record->updatedAt = RecordTimestamps::now();
        $record->save();

        return $record;
    }

    /** @return list<MediaItemRecord> */
    public function list(): array
    {
        return MediaItemRecord::select()
            ->orderBy('createdAt', Direction::DESC)
            ->all();
    }
}
