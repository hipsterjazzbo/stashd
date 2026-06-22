<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\System\Event\SseConnectionRecord;
use App\System\Event\SseConnectionRepository;
use Tempest\Database\PrimaryKey;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

test('tryAcquireSlot rejects once at capacity and accepts again after release', function (): void {
    $connections = $this->container->get(SseConnectionRepository::class);

    $first = $connections->tryAcquireSlot(2, 15);
    $second = $connections->tryAcquireSlot(2, 15);
    $third = $connections->tryAcquireSlot(2, 15);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($third)->toBeNull();

    $connections->release($first);

    expect($connections->tryAcquireSlot(2, 15))->not->toBeNull();
});

test('tryAcquireSlot prunes stale connections before counting toward capacity', function (): void {
    $connections = $this->container->get(SseConnectionRepository::class);

    $slot = $connections->tryAcquireSlot(1, 15);
    expect($slot)->not->toBeNull();

    // Simulate a connection whose worker died without releasing its slot:
    // age its heartbeat well past the staleness threshold.
    $record = SseConnectionRecord::findById(new PrimaryKey($slot->toString()));
    $record->updatedAt = DateTime::now(Timezone::UTC)->minusSeconds(100);
    $record->save();

    expect($connections->tryAcquireSlot(1, 15))->not->toBeNull();
});

test('heartbeat keeps a connection from being pruned as stale', function (): void {
    $connections = $this->container->get(SseConnectionRepository::class);

    $slot = $connections->tryAcquireSlot(1, 15);
    expect($slot)->not->toBeNull();

    $connections->heartbeat($slot);

    // Still within the (generous) staleness window, so it still counts.
    expect($connections->tryAcquireSlot(1, 15))->toBeNull();
});
