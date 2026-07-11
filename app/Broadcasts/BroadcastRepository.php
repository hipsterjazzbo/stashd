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
            ->get($id->toPrimaryKey());
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

    public function findPodcastByTokenSecretId(string $secretId): ?BroadcastRecord
    {
        $broadcast = BroadcastRecord::select()
            ->include('tokenSecretId')
            ->where('type = ? AND tokenSecretId = ?', 'podcast', $secretId)
            ->first();

        return $broadcast instanceof BroadcastRecord ? $broadcast : null;
    }

    /**
     * Returns `$base` if it is free within this stash, otherwise the lowest
     * unused `$base-N` (N starts at 2) -- mirrors StashRepository's slug
     * dedup so an auto-generated broadcast name doesn't collide the second
     * time a user adds the same broadcast type to a stash.
     */
    public function nextAvailableSlug(StashId $stashId, string $base): string
    {
        $taken = array_map(
            static fn (BroadcastRecord $broadcast): string => $broadcast->slug,
            BroadcastRecord::select()
                ->where('stashId = ? AND (slug = ? OR slug LIKE ?)', $stashId->toString(), $base, $base . '-%')
                ->all(),
        );

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $usedOrdinals = [];

        foreach ($taken as $slug) {
            if (preg_match('/^' . preg_quote($base, '/') . '-(\d+)$/', $slug, $match)) {
                $usedOrdinals[(int) $match[1]] = true;
            }
        }

        $ordinal = 2;

        while (isset($usedOrdinals[$ordinal])) {
            $ordinal++;
        }

        return "{$base}-{$ordinal}";
    }

}
