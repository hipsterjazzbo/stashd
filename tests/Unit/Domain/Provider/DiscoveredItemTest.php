<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider;

use App\Providers\Core\DiscoveredItem;
use App\Providers\StashdUri;

test('discovered item toArray includes description when present', function (): void {
    $item = new DiscoveredItem(
        providerItemId: 'demo-1',
        canonicalUri: StashdUri::fake('item/demo-1'),
        title: 'Demo Item',
        description: 'Demo description.',
    );

    expect(DiscoveredItem::toArray($item)['description'])->toBe('Demo description.');
});

test('discovered item toArray includes null description when absent', function (): void {
    $item = new DiscoveredItem(
        providerItemId: 'demo-1',
        canonicalUri: StashdUri::fake('item/demo-1'),
        title: 'Demo Item',
    );

    expect(DiscoveredItem::toArray($item)['description'])->toBeNull();
});
