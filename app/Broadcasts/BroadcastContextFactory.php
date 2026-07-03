<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Stashes\StashItemRepository;
use App\Stashes\StashItemState;
use App\Stashes\StashRepository;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemRepository;
use App\Vault\MediaItemState;

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

    public function build(BroadcastRecord $broadcast): BroadcastContext
    {
        $stashId = $broadcast->stashId;

        $stash = $this->stashes->find($stashId)
            ?? throw BroadcastException::withCode('stash_not_found', 'Stash not found.');

        $stashItems = $this->stashItems->listForStash($stashId);

        $mediaItems = [];
        $vaultOriginals = [];

        foreach ($stashItems as $stashItem) {
            $mediaItemId = (string) $stashItem->mediaItemId;
            $mediaItem = $this->mediaItems->find($stashItem->mediaItemId);

            if ($mediaItem === null) {
                continue;
            }

            $mediaItems[$mediaItemId] = $mediaItem;

            if ($mediaItem->state !== MediaItemState::Ready) {
                $vaultOriginals[$mediaItemId] = null;

                continue;
            }

            $vaultOriginal = $this->assets->findByMediaItemAndRole(
                $stashItem->mediaItemId,
                AssetRole::VaultOriginal,
            );

            if (
                $vaultOriginal === null
                || $vaultOriginal->state !== AssetState::Ready
                || $vaultOriginal->path === null
            ) {
                $vaultOriginals[$mediaItemId] = null;

                continue;
            }

            $vaultOriginals[$mediaItemId] = $vaultOriginal;
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

            $vault = $context->vaultOriginals[(string) $stashItem->mediaItemId] ?? null;

            if ($vault === null) {
                continue;
            }

            $items[] = $stashItem;
        }

        return $items;
    }
}
