<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Stashes\StashId;
use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class BroadcastRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    /** @param array<string, mixed>|null $settings */
    public function create(
        StashId $stashId,
        string $type,
        string $name,
        string $slug,
        BroadcastState $state = BroadcastState::Pending,
        ?array $settings = null,
    ): BroadcastRecord {
        $id = $this->ids->generate('broadcast')->toString();
        $record = new BroadcastRecord(
            stashId: $stashId,
            type: $type,
            name: $name,
            slug: $slug,
            state: $state,
            settings: $settings,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(BroadcastRecord::class)->insert($record)->execute();

        return BroadcastRecord::select()
            ->include('tokenSecretId')
            ->get(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist broadcast record.');
    }

    public function find(BroadcastId $id): ?BroadcastRecord
    {
        return BroadcastRecord::select()
            ->include('tokenSecretId')
            ->get(new PrimaryKey($id->toString()));
    }

    public function save(BroadcastRecord $record): BroadcastRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    /** @return list<BroadcastRecord> */
    public function listForStash(StashId $stashId): array
    {
        return BroadcastRecord::select()
            ->include('tokenSecretId')
            ->where('stashId = ?', $stashId->toString())
            ->orderBy('createdAt', \Tempest\Database\Direction::ASC)
            ->all();
    }

    public function findByStashAndSlug(StashId $stashId, string $slug): ?BroadcastRecord
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
            ->include('tokenSecretId')
            ->where('tokenSecretId IS NOT NULL AND type = ?', 'podcast')
            ->orderBy('createdAt', \Tempest\Database\Direction::ASC)
            ->all();
    }
}
