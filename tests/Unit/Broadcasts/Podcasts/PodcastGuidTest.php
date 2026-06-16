<?php

declare(strict_types=1);

namespace Tests\Unit\Broadcasts\Podcasts;

use App\Broadcasts\BroadcastItemRecord;
use App\Broadcasts\BroadcastItemState;
use App\Broadcasts\Podcasts\PodcastGuid;
use Tempest\Database\PrimaryKey;

test('podcast guid is stable and does not include tokens or paths', function (): void {
    $item = new BroadcastItemRecord(
        broadcastId: 'broadcast_01TEST',
        stashItemId: 'stashitem_01TEST',
        mediaItemId: 'media_01TEST',
        state: BroadcastItemState::Ready,
        tokenPreview: 'abcd...wxyz',
        publishedPath: '/media/vault/private/original.mp3',
    );
    $item->id = new PrimaryKey('bitem_01TEST');

    $guid = (new PodcastGuid())->forItem($item);

    expect($guid)->toBe('stashd:broadcast:broadcast_01TEST:item:bitem_01TEST')
        ->and($guid)->not->toContain('abcd')
        ->and($guid)->not->toContain('/media/');
});
