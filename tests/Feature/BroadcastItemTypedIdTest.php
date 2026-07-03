<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Broadcasts\BroadcastId;
use App\Broadcasts\BroadcastItemId;
use App\Broadcasts\BroadcastItemRepository;
use App\Broadcasts\BroadcastRepository;
use App\Stashes\StashId;
use App\Stashes\StashItemId;
use App\Stashes\StashItemRepository;
use App\Stashes\StashRepository;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;

test('BroadcastItemRecord::broadcastId/stashItemId/mediaItemId round-trip as typed IDs through insert, where-lookup, and reload', function (): void {
    $stashes = $this->container->get(StashRepository::class);
    $broadcasts = $this->container->get(BroadcastRepository::class);
    $mediaItems = $this->container->get(MediaItemRepository::class);
    $stashItems = $this->container->get(StashItemRepository::class);
    $broadcastItems = $this->container->get(BroadcastItemRepository::class);

    $stash = $stashes->create('Broadcast Typed ID Stash', 'broadcast-typed-id-stash-' . bin2hex(random_bytes(3)));
    $stashId = StashId::parse((string) $stash->id);
    $mediaItem = $mediaItems->create(
        providerKey: 'fake',
        providerItemId: 'broadcast-typed-id-item',
        canonicalUri: 'fake://item/broadcast-typed-id-item',
        title: 'Broadcast Typed ID Item',
    );
    $mediaItemId = MediaItemId::parse((string) $mediaItem->id);
    $stashItem = $stashItems->create(stashId: $stashId, mediaItemId: $mediaItemId);
    $stashItemId = StashItemId::parse((string) $stashItem->id);

    $broadcast = $broadcasts->create(
        stashId: $stashId,
        type: 'jellyfin',
        name: 'Broadcast Typed ID Test',
        slug: 'broadcast-typed-id-test-' . bin2hex(random_bytes(3)),
    );
    $broadcastId = BroadcastId::parse((string) $broadcast->id);

    $created = $broadcastItems->create(
        broadcastId: $broadcastId,
        stashItemId: $stashItemId,
        mediaItemId: $mediaItemId,
    );

    expect($created->broadcastId)->toBeInstanceOf(BroadcastId::class)
        ->and($created->broadcastId->toString())->toBe($broadcastId->toString())
        ->and($created->stashItemId)->toBeInstanceOf(StashItemId::class)
        ->and($created->mediaItemId)->toBeInstanceOf(MediaItemId::class);

    // Multi-column WHERE lookup on typed-ID-backed columns: the property
    // caster only fixes hydration/persistence, not raw bound-param binding,
    // so this proves findByBroadcastAndStashItem's explicit ->toString() calls work.
    $found = $broadcastItems->findByBroadcastAndStashItem($broadcastId, $stashItemId);
    expect($found)->not->toBeNull()
        ->and((string) $found->id)->toBe((string) $created->id);

    $reloaded = $broadcastItems->find(BroadcastItemId::parse((string) $created->id));
    expect($reloaded?->broadcastId)->toBeInstanceOf(BroadcastId::class)
        ->and($reloaded?->broadcastId->toString())->toBe($broadcastId->toString())
        ->and($reloaded?->mediaItemId->toString())->toBe($mediaItemId->toString());
});
