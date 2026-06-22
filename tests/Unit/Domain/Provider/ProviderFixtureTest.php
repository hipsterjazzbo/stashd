<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider;

use App\Providers\Fake\FakeProvider;
use App\Providers\StashdUri;

test('fake provider matches committed fixture expectations', function (): void {
    $fixture = json_decode(
        file_get_contents(__DIR__ . '/../../../fixtures/providers/fake/channel_demo.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $provider = new FakeProvider();
    $input = $provider->resolveInput(StashdUri::parse($fixture['source_uri']));
    $strategy = $provider->discoveryStrategies()[0];

    expect($input->providerKey)->toBe($fixture['resolved_input']['provider_key'])
        ->and($input->inputType)->toBe($fixture['resolved_input']['input_type'])
        ->and($input->providerInputId)->toBe($fixture['resolved_input']['provider_input_id'])
        ->and($strategy->key)->toBe($fixture['discovery']['strategy_key']);

    $initial = $provider->discover($input, $strategy);
    expect($initial)->toHaveCount($fixture['discovery']['initial_item_count'])
        ->and($initial[0]->providerItemId)->toBe($fixture['discovery']['first_item_id']);

    $provider->advanceSyncGeneration($input->providerInputId);
    $incremental = $provider->discover($input, $strategy);
    expect($incremental)->toHaveCount($fixture['discovery']['incremental_item_count']);
});
