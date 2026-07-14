<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Support\PrefixedUlidGenerator;
use App\Vault\MediaItemId;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

use function Tempest\Database\query;

final class TimelineEntryRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    /** @return list<TimelineEntryRecord> */
    public function listForMediaItem(MediaItemId $mediaItemId): array
    {
        return TimelineEntryRecord::select()
            ->where('mediaItemId', $mediaItemId->toString())
            ->orderBy('startSeconds', Direction::ASC)
            ->orderBy('endSeconds', Direction::ASC)
            ->all();
    }

    public function findBySourceAndExternalId(
        MediaItemId $mediaItemId,
        TimelineEntrySource $source,
        string $externalId,
    ): ?TimelineEntryRecord {
        return TimelineEntryRecord::select()
            ->where('mediaItemId', $mediaItemId->toString())
            ->where('source', $source)
            ->where('externalId', $externalId)
            ->first();
    }

    public function save(TimelineEntryRecord $entry): TimelineEntryRecord
    {
        $entry->updatedAt = DateTime::now(Timezone::UTC);
        $entry->save();

        return $entry;
    }

    public function create(
        MediaItemId $mediaItemId,
        TimelineEntrySource $source,
        TimelineEntryKind $kind,
        TimelineEntryCategory $category,
        float $startSeconds,
        float $endSeconds,
        ?string $title = null,
        ?string $externalId = null,
        ?array $raw = null,
    ): TimelineEntryRecord {
        $entry = new TimelineEntryRecord(
            mediaItemId: $mediaItemId,
            source: $source,
            kind: $kind,
            category: $category,
            startSeconds: $startSeconds,
            endSeconds: $endSeconds,
            title: $title,
            externalId: $externalId,
            raw: $raw,
            lastCheckedAt: DateTime::now(Timezone::UTC),
        );
        $entry->id = new PrimaryKey($this->ids->generate('timeline')->toString());
        $entry->createdAt = DateTime::now(Timezone::UTC);
        $entry->updatedAt = $entry->createdAt;

        query(TimelineEntryRecord::class)->insert($entry)->execute();

        return $entry;
    }
}
