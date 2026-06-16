<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Provider;

use App\Domain\Provider\Fake\FakeProvider;
use App\Domain\Provider\StrategyCost;
use App\Domain\Provider\StrategyPurpose;
use App\Services\Provider\ProviderStrategySelector;

test('provider strategy selector picks the lowest cost discovery strategy', function (): void {
    $provider = new FakeProvider();
    $selector = new ProviderStrategySelector();

    $strategy = $selector->select($provider, StrategyPurpose::Discovery);

    expect($strategy->key)->toBe('fake.feed')
        ->and($strategy->cost)->toBe(StrategyCost::Low)
        ->and($strategy->purpose)->toBe(StrategyPurpose::Discovery);
});

test('provider strategy selector picks metadata and download strategies for fake provider', function (): void {
    $provider = new FakeProvider();
    $selector = new ProviderStrategySelector();

    expect($selector->select($provider, StrategyPurpose::Metadata)->key)->toBe('fake.metadata')
        ->and($selector->select($provider, StrategyPurpose::Download)->key)->toBe('fake.download');
});
