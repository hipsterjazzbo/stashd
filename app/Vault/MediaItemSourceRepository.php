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

final class MediaItemSourceRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $mediaItemId,
        string $providerKey,
        string $providerInputId,
        string $discoveredUri,
        ?PrefixedUlid $stashInputId = null,
        ?int $position = null,
    ): MediaItemSourceRecord {
        $id = $this->ids->generate('source')->toString();
        $record = new MediaItemSourceRecord(
            mediaItemId: $mediaItemId->toString(),
            providerKey: $providerKey,
            providerInputId: $providerInputId,
            discoveredUri: $discoveredUri,
            discoveredAt: DateTime::now(Timezone::UTC),
            stashInputId: $stashInputId?->toString(),
            position: $position,
        );
        $record->id = new PrimaryKey($id);

        query(MediaItemSourceRecord::class)->insert($record)->execute();

        return MediaItemSourceRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist media item source record.');
    }

    public function findForMediaItemAndInput(
        PrefixedUlid $mediaItemId,
        PrefixedUlid $stashInputId,
    ): ?MediaItemSourceRecord {
        return MediaItemSourceRecord::select()
            ->where('mediaItemId = ? AND stashInputId = ?', $mediaItemId->toString(), $stashInputId->toString())
            ->first();
    }

    public function deleteForStashInput(string|\Stringable $stashInputId): void
    {
        $sources = MediaItemSourceRecord::select()
            ->where('stashInputId = ?', (string) $stashInputId)
            ->all();

        foreach ($sources as $source) {
            $source->delete();
        }
    }
}
