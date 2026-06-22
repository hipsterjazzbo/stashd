<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stashes;

use App\Stashes\Api\StashInputResource;
use App\Stashes\StashInputOptions;
use App\Stashes\StashInputRecord;
use App\Stashes\StashInputState;
use App\Stashes\StashInputType;
use App\Stashes\SyncMode;
use Tempest\Database\PrimaryKey;

test('provider option keys survive encoding untouched, even with digit-uppercase adjacency', function (): void {
    $input = new StashInputRecord(
        stashId: 'stash_01ARZ3NDEKTSV4RRFFQ69G5FAV',
        providerKey: 'fake',
        inputType: StashInputType::Channel,
        sourceUri: 'fake://channel/demo',
        providerInputId: 'channel:demo',
        state: StashInputState::Ready,
        syncMode: SyncMode::Automatic,
        optionsJson: new StashInputOptions(provider: ['weird_01Key' => true]),
    );
    $input->id = new PrimaryKey('input_01ARZ3NDEKTSV4RRFFQ69G5FAV');

    $resource = StashInputResource::fromRecord($input)->toArray();

    expect($resource['options']['provider'])->toBe(['weird_01Key' => true]);
});
