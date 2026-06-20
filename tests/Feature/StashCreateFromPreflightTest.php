<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Stashes\StashInputRecord;
use App\Stashes\StashItemRecord;
use App\Stashes\StashRecord;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemRecord;
use App\Vault\MediaItemState;
use Tempest\Database\PrimaryKey;
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

test('create from preflight persists discovered descriptions onto new media items', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/with-descriptions'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'with-descriptions',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $media = MediaItemRecord::select()
        ->where('providerKey = ? AND providerItemId = ?', 'fake', 'with-descriptions-episode-1')
        ->first();

    expect($media)->not->toBeNull()
        ->and($media->description)->toBe('Fake episode 1 description.');
});

test('create from preflight leaves existing media item description unchanged when reused', function (): void {
    $headers = $this->authHeaders();
    $mediaItems = $this->container->get(\App\Vault\MediaItemRepository::class);

    $mediaItems->create(
        providerKey: 'fake',
        providerItemId: 'dedupe-desc-episode-1',
        canonicalUri: 'fake://item/dedupe-desc-episode-1',
        title: 'Existing Episode',
        description: 'Manually curated description.',
    );

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/dedupe-desc'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'dedupe-desc',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $media = MediaItemRecord::select()
        ->where('providerKey = ? AND providerItemId = ?', 'fake', 'dedupe-desc-episode-1')
        ->first();

    expect($media->description)->toBe('Manually curated description.');
});

test('create from preflight with video policy automatically downloads items without a manual command', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/auto-download'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'auto-download',
            'download_policy' => 'video',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $result = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
    $stashItems = StashItemRecord::select()
        ->where('stashId = ?', $result->body['command']['result']['stash_id'])
        ->all();

    expect($stashItems)->toHaveCount(3);

    $assets = $this->container->get(AssetRepository::class);

    foreach ($stashItems as $stashItem) {
        $media = MediaItemRecord::findById(new PrimaryKey((string) $stashItem->mediaItemId));
        expect($media?->state)->toBe(MediaItemState::Ready);

        $original = $assets->findByMediaItemAndRole(
            \App\Support\PrefixedUlid::parse((string) $stashItem->mediaItemId),
            AssetRole::VaultOriginal,
        );
        expect($original?->state)->toBe(AssetState::Ready);
    }
});

test('create from preflight with metadata_only policy enqueues no downloads', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/no-auto-download'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'no-auto-download',
            'download_policy' => 'metadata_only',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $result = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
    $stashItems = StashItemRecord::select()
        ->where('stashId = ?', $result->body['command']['result']['stash_id'])
        ->all();

    expect($stashItems)->toHaveCount(3);

    foreach ($stashItems as $stashItem) {
        $media = MediaItemRecord::findById(new PrimaryKey((string) $stashItem->mediaItemId));
        expect($media?->state)->toBe(MediaItemState::Discovered);
    }

    expect(\App\Commands\CommandRecord::select()->where('type = ?', 'item.download')->all())->toHaveCount(0);
});

test('create from preflight assigns ordinal-suffixed slugs when names collide', function (): void {
    $headers = $this->authHeaders();

    $createFromName = function (string $sourceUri) use ($headers): string {
        $preflight = $this->http->post('/api/v1/commands', [
            'type' => 'stash.preflight',
            'options' => ['source_uri' => $sourceUri],
        ], headers: $headers)->assertStatus(Status::CREATED);
        $this->processAllJobs();

        $create = $this->http->post('/api/v1/commands', [
            'type' => 'stash.create_from_preflight',
            'options' => [
                'preflight_command_id' => $preflight->body['command_id'],
                'name' => 'Duplicate Name',
            ],
        ], headers: $headers)->assertStatus(Status::CREATED);
        $this->processAllJobs();

        $result = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
        $result->assertOk();

        $stash = StashRecord::findById(new PrimaryKey($result->body['command']['result']['stash_id']));

        return $stash->slug;
    };

    expect($createFromName('fake://channel/slug-collision-1'))->toBe('duplicate-name')
        ->and($createFromName('fake://channel/slug-collision-2'))->toBe('duplicate-name-1')
        ->and($createFromName('fake://channel/slug-collision-3'))->toBe('duplicate-name-2');
});

test('next available slug fills gaps and accounts for literal ordinal-suffixed slugs', function (): void {
    $stashes = $this->container->get(\App\Stashes\StashRepository::class);

    expect($stashes->nextAvailableSlug('gap-test'))->toBe('gap-test');

    $stashes->create(name: 'Gap Test', slug: 'gap-test');
    $stashes->create(name: 'Gap Test', slug: 'gap-test-2');

    expect($stashes->nextAvailableSlug('gap-test'))->toBe('gap-test-1');

    $stashes->create(name: 'Gap Test', slug: 'gap-test-1');

    expect($stashes->nextAvailableSlug('gap-test'))->toBe('gap-test-3');
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
