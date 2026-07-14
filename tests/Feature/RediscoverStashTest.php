<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Stashes\RediscoverStash;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;

test('rediscover fills missing discovery metadata without overwriting saved values', function (): void {
    [, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('rediscover-metadata');
    $mediaItems = $this->container->get(MediaItemRepository::class);
    $mediaItem = $mediaItems->find(MediaItemId::parse($mediaItemId));
    $mediaItem->description = null;
    $mediaItem->durationSeconds = null;
    $mediaItem->publishedAt = null;
    $mediaItem->title = 'Saved title';
    $mediaItems->save($mediaItem);

    $result = $this->container->get(RediscoverStash::class)->execute($stashId);
    $reloaded = $mediaItems->find(MediaItemId::parse($mediaItemId));

    expect($result)->toMatchArray(['inputs' => 1, 'discovered' => 3, 'matched' => 3, 'updated' => 1, 'fields' => 3])
        ->and($reloaded->title)->toBe('Saved title')
        ->and($reloaded->description)->toBe('Fake episode 1 description.')
        ->and((int) $reloaded->durationSeconds?->getTotalSeconds())->toBe(630)
        ->and($reloaded->publishedAt?->toRfc3339(useZ: true))->toBe('2026-01-01T12:00:00Z');
});
