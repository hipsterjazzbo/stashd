<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Support\PrefixedUlidGenerator;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;
use App\Vault\MediaItemSourceRepository;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

use function Tempest\Support\str;

final class StashRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
        private StashItemRepository $stashItems,
        private StashInputRepository $stashInputs,
        private MediaItemSourceRepository $mediaItemSources,
        private MediaItemRepository $mediaItems,
    ) {
    }

    public function create(
        string $name,
        string $slug,
        SyncMode $syncMode = SyncMode::Automatic,
        DownloadPolicy $downloadPolicy = DownloadPolicy::Video,
        OrganizationMode $organizationMode = OrganizationMode::Flat,
        ?string $description = null,
        ?string $iconUri = null,
    ): StashRecord {
        $id = $this->ids->generate('stash')->toString();
        $record = new StashRecord(
            name: $name,
            slug: $slug,
            syncMode: $syncMode,
            downloadPolicy: $downloadPolicy,
            organizationMode: $organizationMode,
            state: StashState::Ready,
            description: $description,
            iconUri: $iconUri,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(StashRecord::class)->insert($record)->execute();

        return $record;
    }

    public function find(StashId $id): ?StashRecord
    {
        return StashRecord::findById($id->toPrimaryKey());
    }

    public function findBySlug(string $slug): ?StashRecord
    {
        return StashRecord::select()->where('slug = ?', $slug)->first();
    }

    public function slugify(string $name): string
    {
        $slug = str($name)->slug()->toString();

        return $slug !== '' ? $slug : 'stash';
    }

    /**
     * Returns `$base` if it is free, otherwise the lowest unused `$base-N` (N starts at 1).
     */
    public function nextAvailableSlug(string $base): string
    {
        $taken = array_map(
            static fn (StashRecord $stash): string => $stash->slug,
            StashRecord::select()->where('slug = ? OR slug LIKE ?', $base, $base . '-%')->all(),
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

        $ordinal = 1;

        while (isset($usedOrdinals[$ordinal])) {
            $ordinal++;
        }

        return "{$base}-{$ordinal}";
    }

    /** @return list<StashRecord> */
    public function list(): array
    {
        return StashRecord::select()
            ->orderBy('createdAt', Direction::ASC)
            ->all();
    }

    public function update(
        StashRecord $stash,
        ?string $name = null,
        ?string $description = null,
        ?SyncMode $syncMode = null,
        ?DownloadPolicy $downloadPolicy = null,
        ?OrganizationMode $organizationMode = null,
        ?string $iconUri = null,
    ): StashRecord {
        if ($name !== null) {
            $stash->name = $name;
        }

        if ($description !== null) {
            $stash->description = $description;
        }

        if ($syncMode !== null) {
            $stash->syncMode = $syncMode;
        }

        if ($downloadPolicy !== null) {
            $stash->downloadPolicy = $downloadPolicy;
        }

        if ($organizationMode !== null) {
            $stash->organizationMode = $organizationMode;
        }

        if ($iconUri !== null) {
            $stash->iconUri = $iconUri;
        }

        $stash->updatedAt = DateTime::now(Timezone::UTC);
        $stash->save();

        return $stash;
    }

    /**
     * Deletes the stash and its inputs/items (and their media item sources).
     * Deduped `media_items` and Vault originals are left intact.
     */
    public function delete(StashRecord $stash): void
    {
        $stashId = StashId::fromPrimaryKey($stash->id);

        foreach ($this->stashItems->listForStash($stashId) as $item) {
            $item->delete();
        }

        foreach ($this->stashInputs->listForStash($stashId) as $input) {
            $this->mediaItemSources->deleteForStashInput(StashInputId::fromPrimaryKey($input->id));
            $input->delete();
        }

        $stash->delete();
    }

    /**
     * For this stash's items, reports which media items are still referenced by
     * other stashes (shared) versus which would become orphaned in the Vault.
     *
     * @return array{sharedItems: list<array<string, mixed>>, orphanedItems: list<array<string, mixed>>}
     */
    public function deleteImpact(StashRecord $stash): array
    {
        $stashId = StashId::fromPrimaryKey($stash->id);

        $mediaItemIds = array_values(array_unique(array_map(
            static fn (StashItemRecord $item): string => (string) $item->mediaItemId,
            $this->stashItems->listForStash($stashId),
        )));

        if ($mediaItemIds === []) {
            return ['sharedItems' => [], 'orphanedItems' => []];
        }

        $otherStashIdsByMediaItemId = [];

        foreach ($this->stashItems->listForMediaItemsExcludingStash($mediaItemIds, $stashId) as $otherItem) {
            $otherStashIdsByMediaItemId[(string) $otherItem->mediaItemId][(string) $otherItem->stashId] = true;
        }

        $stashNamesById = [];

        foreach ($otherStashIdsByMediaItemId as $otherStashIds) {
            foreach (array_keys($otherStashIds) as $otherStashId) {
                $stashNamesById[$otherStashId] ??= $this->find(StashId::parse($otherStashId))?->name ?? 'Unknown stash';
            }
        }

        $sharedItems = [];
        $orphanedItems = [];

        foreach ($mediaItemIds as $mediaItemId) {
            $title = $this->mediaItems->find(MediaItemId::parse($mediaItemId))?->title ?? $mediaItemId;
            $otherStashIds = array_keys($otherStashIdsByMediaItemId[$mediaItemId] ?? []);

            if ($otherStashIds === []) {
                $orphanedItems[] = ['mediaItemId' => $mediaItemId, 'title' => $title];

                continue;
            }

            $sharedItems[] = [
                'mediaItemId' => $mediaItemId,
                'title' => $title,
                'sharedWithStashes' => array_map(
                    static fn (string $otherStashId): array => ['id' => $otherStashId, 'name' => $stashNamesById[$otherStashId]],
                    $otherStashIds,
                ),
            ];
        }

        return ['sharedItems' => $sharedItems, 'orphanedItems' => $orphanedItems];
    }
}
