<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Stashes\StashItemRepository;
use App\Stashes\StashItemState;
use App\Stashes\StashRepository;
use App\Support\PrefixedUlid;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemRepository;
use App\Vault\MediaItemState;
use Tempest\Database\Direction;

final readonly class BroadcastContextFactory
{
    public function __construct(
        private BroadcastRepository $broadcasts,
        private StashRepository $stashes,
        private StashItemRepository $stashItems,
        private MediaItemRepository $mediaItems,
        private AssetRepository $assets,
    ) {
    }

    public function build(PrefixedUlid $broadcastId): BroadcastContext
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        $stash = $this->stashes->find(PrefixedUlid::parse($broadcast->stashId))
            ?? throw BroadcastException::withCode('stash_not_found', 'Stash not found.');

        $stashItems = \App\Stashes\StashItemRecord::select()
            ->where('stashId = ?', $broadcast->stashId)
            ->orderBy('position', Direction::ASC)
            ->all();

        $mediaItems = [];
        $vaultOriginals = [];

        foreach ($stashItems as $stashItem) {
            $mediaItem = $this->mediaItems->find(PrefixedUlid::parse($stashItem->mediaItemId));

            if ($mediaItem === null) {
                continue;
            }

            $mediaItems[$stashItem->mediaItemId] = $mediaItem;

            if ($mediaItem->state !== MediaItemState::Ready) {
                $vaultOriginals[$stashItem->mediaItemId] = null;

                continue;
            }

            $vaultOriginal = $this->assets->findByMediaItemAndRole(
                PrefixedUlid::parse($stashItem->mediaItemId),
                AssetRole::VaultOriginal,
            );

            if (
                $vaultOriginal === null
                || $vaultOriginal->state !== AssetState::Ready
                || $vaultOriginal->path === null
            ) {
                $vaultOriginals[$stashItem->mediaItemId] = null;

                continue;
            }

            $vaultOriginals[$stashItem->mediaItemId] = $vaultOriginal;
        }

        return new BroadcastContext(
            broadcast: $broadcast,
            stash: $stash,
            stashItems: $stashItems,
            mediaItems: $mediaItems,
            vaultOriginals: $vaultOriginals,
        );
    }

    /** @return list<\App\Stashes\StashItemRecord> */
    public function publishableStashItems(BroadcastContext $context): array
    {
        $items = [];

        foreach ($context->stashItems as $stashItem) {
            if ($stashItem->state !== StashItemState::Active) {
                continue;
            }

            $vault = $context->vaultOriginals[$stashItem->mediaItemId] ?? null;

            if ($vault === null) {
                continue;
            }

            $items[] = $stashItem;
        }

        return $items;
    }
}
