<?php

declare(strict_types=1);

namespace Tests\Unit\Broadcasts\Podcasts;

use App\Broadcasts\BroadcastId;
use App\Broadcasts\BroadcastItemRecord;
use App\Broadcasts\BroadcastItemState;
use App\Broadcasts\Podcasts\PodcastGuid;
use App\Stashes\StashItemId;
use App\Vault\MediaItemId;
use Tempest\Database\PrimaryKey;

test('podcast guid is stable and does not include tokens or paths', function (): void {
    $item = new BroadcastItemRecord(
        broadcastId: BroadcastId::parse('broadcast_01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        stashItemId: StashItemId::parse('item_01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        mediaItemId: MediaItemId::parse('media_01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        state: BroadcastItemState::Ready,
        tokenPreview: 'abcd...wxyz',
        publishedPath: '/media/vault/private/original.mp3',
    );
    $item->id = new PrimaryKey('bitem_01ARZ3NDEKTSV4RRFFQ69G5FAV');

    $guid = (new PodcastGuid())->forItem($item);

    expect($guid)->toBe('stashd:broadcast:broadcast_01ARZ3NDEKTSV4RRFFQ69G5FAV:item:bitem_01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->and($guid)->not->toContain('abcd')
        ->and($guid)->not->toContain('/media/');
});
