<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tempest\Http\Status;

test('GET /api/v1/stashes lists stashes', function (): void {
    [$headersA, $stashIdA] = $this->bootstrapFakeDownloadStash('phase6-stashes-a');
    [, $stashIdB] = $this->bootstrapFakeDownloadStash('phase6-stashes-b');

    $response = $this->http->get('/api/v1/stashes', headers: $headersA)
        ->assertStatus(Status::OK);

    $ids = array_column($response->body['stashes'], 'id');

    expect($ids)->toContain($stashIdA)
        ->and($ids)->toContain($stashIdB)
        ->and($response->body['stashes'][0])->toHaveKeys([
            'id',
            'name',
            'slug',
            'sync_mode',
            'download_policy',
            'organization_mode',
            'state',
            'created_at',
            'updated_at',
        ]);
});

test('GET /api/v1/stashes/{id} returns stash detail', function (): void {
    [$headers, $stashId] = $this->bootstrapFakeDownloadStash('phase6-stash-detail');

    $this->http->get('/api/v1/stashes/' . $stashId, headers: $headers)
        ->assertStatus(Status::OK)
        ->assertJsonSubset(['stash' => ['id' => $stashId, 'state' => 'ready']]);
});

test('GET /api/v1/stashes/{id} returns 404 for an unknown stash', function (): void {
    [$headers] = $this->bootstrapFakeDownloadStash('phase6-stash-missing');

    $this->http->get('/api/v1/stashes/stash_01ARZ3NDEKTSV4RRFFQ69G5FAV', headers: $headers)
        ->assertStatus(Status::NOT_FOUND)
        ->assertJsonSubset(['error' => ['code' => 'not_found']]);
});

test('GET /api/v1/stashes/{id}/items lists stash items', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('phase6-stash-items');

    $response = $this->http->get('/api/v1/stashes/' . $stashId . '/items', headers: $headers)
        ->assertStatus(Status::OK);

    $mediaItemIds = array_column($response->body['items'], 'media_item_id');

    expect($mediaItemIds)->toContain($mediaItemId)
        ->and($response->body['items'][0])->toHaveKeys(['id', 'stash_id', 'media_item_id', 'state', 'position']);
});

test('GET /api/v1/stashes/{id}/inputs lists stash inputs', function (): void {
    [$headers, $stashId] = $this->bootstrapFakeDownloadStash('phase6-stash-inputs');

    $response = $this->http->get('/api/v1/stashes/' . $stashId . '/inputs', headers: $headers)
        ->assertStatus(Status::OK);

    expect($response->body['inputs'])->toHaveCount(1)
        ->and($response->body['inputs'][0])->toHaveKeys(['id', 'stash_id', 'provider_key', 'source_uri', 'state']);
});

test('GET /api/v1/items lists media items across stashes', function (): void {
    [$headersA, , $mediaItemIdA] = $this->bootstrapFakeDownloadStash('phase6-items-a');
    [, , $mediaItemIdB] = $this->bootstrapFakeDownloadStash('phase6-items-b');

    $response = $this->http->get('/api/v1/items', headers: $headersA)
        ->assertStatus(Status::OK);

    $ids = array_column($response->body['items'], 'id');

    expect($ids)->toContain($mediaItemIdA)
        ->and($ids)->toContain($mediaItemIdB)
        ->and($response->body['items'][0])->toHaveKeys(['id', 'provider_key', 'title', 'state']);
});

test('broadcast rebuild, verify, and prune commands dispatch end-to-end', function (): void {
    [$headers, , , $broadcastId] = $this->bootstrapFakeDownloadBroadcast('phase6-broadcast-actions');

    foreach (['broadcast.rebuild', 'broadcast.verify', 'broadcast.prune'] as $type) {
        $command = $this->http->post('/api/v1/commands', [
            'type' => $type,
            'options' => ['broadcast_id' => $broadcastId],
        ], headers: $headers)->assertStatus(Status::CREATED);

        $this->processAllJobs();

        $show = $this->http->get('/api/v1/commands/' . $command->body['command_id'], headers: $headers)
            ->assertStatus(Status::OK);

        expect($show->body['jobs'][0]['state'])->toBe('ready');
    }
});

test('broadcast rotate_token command dispatches end-to-end for a podcast broadcast', function (): void {
    [$headers, $stashId] = $this->bootstrapFakeDownloadStash('phase6-broadcast-rotate-token');

    $broadcast = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'audio_podcast',
        'name' => 'Phase 6 Rotate Token Podcast',
        'slug' => 'phase6-rotate-token-' . bin2hex(random_bytes(3)),
    ], headers: $headers)->assertStatus(Status::CREATED);

    $command = $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rotate_token',
        'options' => ['broadcast_id' => $broadcast->body['broadcast']['id']],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $show = $this->http->get('/api/v1/commands/' . $command->body['command_id'], headers: $headers)
        ->assertStatus(Status::OK);

    expect($show->body['jobs'][0]['state'])->toBe('ready');
});
