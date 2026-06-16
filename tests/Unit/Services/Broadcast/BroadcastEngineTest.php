<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Broadcast;

use App\Domain\Broadcast\BroadcastItemState;
use App\Domain\Broadcast\BroadcastState;
use App\Services\Broadcast\BroadcastFilenameBuilder;
use App\Services\Broadcast\InodeHelper;

test('broadcast state transitions follow lifecycle rules', function (): void {
    expect(BroadcastState::Pending->canTransitionTo(BroadcastState::Processing))->toBeTrue()
        ->and(BroadcastState::Processing->canTransitionTo(BroadcastState::Ready))->toBeTrue()
        ->and(BroadcastState::Ready->canTransitionTo(BroadcastState::Stale))->toBeTrue()
        ->and(BroadcastState::Stale->canTransitionTo(BroadcastState::Processing))->toBeTrue()
        ->and(BroadcastState::Ready->canTransitionTo(BroadcastState::Pending))->toBeFalse();
});

test('broadcast item state transitions allow stale recovery', function (): void {
    expect(BroadcastItemState::Ready->canTransitionTo(BroadcastItemState::Stale))->toBeTrue()
        ->and(BroadcastItemState::Stale->canTransitionTo(BroadcastItemState::Processing))->toBeTrue()
        ->and(BroadcastItemState::Processing->canTransitionTo(BroadcastItemState::Ready))->toBeTrue();
});

test('inode helper detects hardlinked files', function (): void {
    $dir = sys_get_temp_dir() . '/stashd-inode-' . bin2hex(random_bytes(4));
    mkdir($dir);

    $source = $dir . '/source.txt';
    $target = $dir . '/target.txt';
    file_put_contents($source, 'linked');
    link($source, $target);

    expect(InodeHelper::sameFile($source, $target))->toBeTrue();

    file_put_contents($dir . '/copy.txt', 'linked');
    expect(InodeHelper::sameFile($source, $dir . '/copy.txt'))->toBeFalse();

    unlink($source);
    unlink($target);
    unlink($dir . '/copy.txt');
    rmdir($dir);
});

test('broadcast filename builder produces safe readable names', function (): void {
    $builder = new BroadcastFilenameBuilder();

    expect($builder->seasonFolder(new \App\Domain\Stash\StashItemRecord(
        stashId: 's',
        mediaItemId: 'm',
        state: \App\Domain\Stash\StashItemState::Active,
        seasonNumber: 2,
    )))->toBe('Season 02');
});
