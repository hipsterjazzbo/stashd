<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Stashes\StashItemRecord;
use App\Stashes\StashRecord;
use App\Vault\MediaItemRecord;
use Tempest\Http\Status;

test('youtube preflight command completes asynchronously using fixtures', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678',
            'source_title' => 'YouTube Demo Channel',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $preflight->body['command_id'], headers: $headers);
    $command->assertOk();

    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['resolved_input']['provider_key'])->toBe('youtube')
        ->and($command->body['command']['result']['discovery']['strategy_key'])->toBe('youtube.rss')
        ->and($command->body['command']['result']['discovery']['discovered_items'])->toHaveCount(3);

    $review = $this->http->get(
        '/api/v1/stashes/preflight/' . $preflight->body['command_id'] . '/review',
        headers: $headers,
    );
    $review->assertOk();
    expect($review->body['preflight']['discovery']['discovered_items'][0]['provider_item_id'])->toBe('demoVideo01');
});

test('youtube create from preflight creates domain records without downloading', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'name' => 'YouTube Demo Stash',
            'slug' => 'youtube-demo-stash',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
    $command->assertOk();

    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['media_items_created'])->toBe(3)
        ->and($command->body['command']['result']['stash_items_created'])->toBe(3);

    $media = MediaItemRecord::select()->where('providerKey = ?', 'youtube')->all();
    expect($media)->toHaveCount(3)
        ->and($media[0]->thumbnailUri)->not->toBeNull();
});

test('youtube media items deduplicate across multiple stashes', function (): void {
    $headers = $this->authHeaders();
    $mediaItems = $this->container->get(\App\Vault\MediaItemRepository::class);

    $mediaItems->create(
        providerKey: 'youtube',
        providerItemId: 'demoVideo01',
        canonicalUri: 'https://www.youtube.com/watch?v=demoVideo01',
        title: 'Existing YouTube Item',
    );

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'youtube-dedupe-stash',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $result = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
    $result->assertOk();

    expect($result->body['command']['result']['media_items_created'])->toBe(2)
        ->and($result->body['command']['result']['media_items_reused'])->toBe(1)
        ->and(MediaItemRecord::count()->execute())->toBe(3)
        ->and(StashRecord::count()->execute())->toBe(1)
        ->and(StashItemRecord::count()->execute())->toBe(3);
});

test('youtube preflight review exposes resolved channel identity for a handle source', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'https://www.youtube.com/@StashdDemo',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $review = $this->http->get(
        '/api/v1/stashes/preflight/' . $preflight->body['command_id'] . '/review',
        headers: $headers,
    );
    $review->assertOk();

    expect($review->body['preflight']['resolved_input']['provider_input_id'])->toBe('UCStashdDemoCh0012345678')
        ->and($review->body['preflight']['resolved_input']['source_title'])->toBe('Stashd Demo')
        ->and($review->body['preflight']['resolved_input']['source_avatar_uri'])->toBe('https://yt3.googleusercontent.com/stashd-demo-avatar.jpg')
        ->and($review->body['preflight']['resolved_input']['estimated_item_count'])->toBe(217);
});

test('youtube create from preflight populates stash icon_uri from the resolved channel avatar', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'https://www.youtube.com/@StashdDemo',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $create = $this->http->post('/api/v1/commands', [
        'type' => 'stash.create_from_preflight',
        'options' => [
            'preflight_command_id' => $preflight->body['command_id'],
            'slug' => 'youtube-handle-icon-stash',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $create->body['command_id'], headers: $headers);
    $command->assertOk();
    expect($command->body['command']['state'])->toBe('completed');

    $stash = StashRecord::findById(new \Tempest\Database\PrimaryKey($command->body['command']['result']['stash_id']));
    expect($stash)->not->toBeNull()
        ->and($stash->iconUri)->toBe('https://yt3.googleusercontent.com/stashd-demo-avatar.jpg');
});

test('unsupported youtube url fails preflight job with stable error', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'https://www.youtube.com/feed/trending',
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $preflight->body['command_id'], headers: $headers);
    $command->assertOk();

    expect($command->body['command']['state'])->toBe('failed');

    $jobId = $command->body['jobs'][0]['id'];
    $job = $this->http->get('/api/v1/jobs/' . $jobId, headers: $headers);
    $job->assertOk();
    expect($job->body['job']['state'])->toBe('failed')
        ->and($job->body['job']['last_error'])->toContain('Unsupported YouTube URL');
});
