<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider;

use App\Domain\Provider\Fake\FakeProvider;
use App\Domain\Provider\StashdUri;

test('fake provider discovers three channel items initially', function (): void {
    $provider = new FakeProvider();
    $input = $provider->resolveInput(StashdUri::parse('fake://channel/demo'));
    $strategy = $provider->discoveryStrategies()[0];

    $items = $provider->discover($input, $strategy);

    expect($items)->toHaveCount(3)
        ->and($items[0]->providerItemId)->toBe('demo-episode-1');
});

test('fake provider adds a fourth item on subsequent sync', function (): void {
    $provider = new FakeProvider();
    $input = $provider->resolveInput(StashdUri::parse('fake://channel/demo'));
    $strategy = $provider->discoveryStrategies()[0];

    $provider->discover($input, $strategy);
    $items = $provider->discover($input, $strategy);

    expect($items)->toHaveCount(4);
});

test('fake provider discovers twenty playlist items', function (): void {
    $provider = new FakeProvider();
    $input = $provider->resolveInput(StashdUri::parse('fake://playlist/long'));
    $strategy = $provider->discoveryStrategies()[0];

    expect($provider->discover($input, $strategy))->toHaveCount(20);
});
