<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use App\Support\RecordTimestamps;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class BroadcastRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        PrefixedUlid $stashId,
        BroadcastType $type,
        string $name,
        string $slug,
        BroadcastState $state = BroadcastState::Pending,
        ?array $settings = null,
    ): BroadcastRecord {
        $id = $this->ids->generate('broadcast')->toString();
        $record = new BroadcastRecord(
            stashId: $stashId->toString(),
            type: $type,
            name: $name,
            slug: $slug,
            state: $state,
            settingsJson: $settings === null ? null : json_encode($settings, JSON_THROW_ON_ERROR),
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(BroadcastRecord::class)->insert($record)->execute();

        return BroadcastRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist broadcast record.');
    }

    public function find(PrefixedUlid $id): ?BroadcastRecord
    {
        return BroadcastRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(BroadcastRecord $record): BroadcastRecord
    {
        $record->updatedAt = RecordTimestamps::now();
        $record->save();

        return $record;
    }

    /** @return list<BroadcastRecord> */
    public function listForStash(PrefixedUlid $stashId): array
    {
        return BroadcastRecord::select()
            ->where('stashId = ?', $stashId->toString())
            ->orderBy('createdAt', \Tempest\Database\Direction::ASC)
            ->all();
    }

    public function findByStashAndSlug(PrefixedUlid $stashId, string $slug): ?BroadcastRecord
    {
        return BroadcastRecord::select()
            ->where('stashId = ? AND slug = ?', $stashId->toString(), $slug)
            ->first();
    }

    /**
     * Podcast broadcasts that carry a feed token, used to resolve a raw feed
     * token back to its broadcast for the public feed route.
     *
     * @return list<BroadcastRecord>
     */
    public function listPodcastBroadcastsWithFeedToken(): array
    {
        return BroadcastRecord::select()
            ->where(
                'tokenSecretId IS NOT NULL AND (type = ? OR type = ?)',
                BroadcastType::AudioPodcast->value,
                BroadcastType::VideoPodcast->value,
            )
            ->orderBy('createdAt', \Tempest\Database\Direction::ASC)
            ->all();
    }
}
