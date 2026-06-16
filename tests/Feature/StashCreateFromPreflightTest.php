<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Stashes\StashInputRecord;
use App\Stashes\StashItemRecord;
use App\Stashes\StashRecord;
use App\Vault\MediaItemRecord;
use Tempest\Http\Status;

test('create from preflight command creates stash domain records asynchronously', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'fake://channel/create-from-preflight',
            'source_title' => 'Create From Preflight Channel',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'name' => 'Preflight Channel Stash',
            'slug' => 'preflight-channel-stash',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
    $command->assertOk();
    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['stash_id'])->toStartWith('stash_')
        ->and($command->body['command']['result']['media_items_created'])->toBe(3)
        ->and($command->body['command']['result']['stash_items_created'])->toBe(3);

    $stash = StashRecord::findById(new \Tempest\Database\PrimaryKey($command->body['command']['result']['stash_id']));
    expect($stash)->not->toBeNull()
        ->and($stash->slug)->toBe('preflight-channel-stash');

    $input = StashInputRecord::findById(new \Tempest\Database\PrimaryKey($command->body['command']['result']['stash_input_id']));
    expect($input)->not->toBeNull()
        ->and($input->sourceUri)->toBe('fake://channel/create-from-preflight');

    expect(MediaItemRecord::count()->execute())->toBe(3)
        ->and(StashItemRecord::count()->execute())->toBe(3);
});

test('create from preflight reuses existing media items by provider identity', function (): void {
    $headers = $this->authHeaders();
    $mediaItems = $this->container->get(\App\Vault\MediaItemRepository::class);

    $mediaItems->create(
        providerKey: 'fake',
        providerItemId: 'dedupe-a-episode-1',
        canonicalUri: 'fake://item/dedupe-a-episode-1',
        title: 'Existing Episode',
    );

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/dedupe-a'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'dedupe-a',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $result = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
    $result->assertOk();
    expect($result->body['command']['result']['media_items_created'])->toBe(2)
        ->and($result->body['command']['result']['media_items_reused'])->toBe(1)
        ->and(MediaItemRecord::count()->execute())->toBe(3);
});

test('create from preflight rejects incomplete preflight commands', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/incomplete'],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'should-not-create',
        ],
    ], headers: $headers);

    $response->assertStatus(Status::BAD_REQUEST);
    expect($response->body['error']['code'])->toBe('validation_error');
});

test('preflight review exposes discovered items for commit flow', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/stashes/preflight', [
        'source_uri' => 'fake://channel/review-items',
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $review = $this->http->get(
        '/api/v1/stashes/preflight/' . $preflight->body['command_id'] . '/review',
        headers: $headers,
    );
    $review->assertOk();
    expect($review->body['preflight']['discovery']['discovered_items'])->toHaveCount(3)
        ->and($review->body['preflight']['discovery']['sample_items'])->toHaveCount(3);
});
