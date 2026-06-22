<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tempest\Http\Status;

test('item stashes and broadcasts endpoints return 404 for an unknown item', function (): void {
    $headers = $this->authHeaders();

    $this->http->get('/api/v1/items/media_01ARZ3NDEKTSV4RRFFQ69G5FAV/stashes', headers: $headers)
        ->assertStatus(Status::NOT_FOUND);
    $this->http->get('/api/v1/items/media_01ARZ3NDEKTSV4RRFFQ69G5FAV/broadcasts', headers: $headers)
        ->assertStatus(Status::NOT_FOUND);
});

test('item exposes the stashes that contain it, deduplicated, and none when added to only one', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('membership-demo');

    $response = $this->http->get("/api/v1/items/{$mediaItemId}/stashes", headers: $headers);
    $response->assertOk();

    $stashIds = array_column($response->body['stashes'], 'id');
    expect($stashIds)->toBe([$stashId]);
});

test('item exposes the broadcasts that include it', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('membership-broadcast-demo');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rebuild',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $response = $this->http->get("/api/v1/items/{$mediaItemId}/broadcasts", headers: $headers);
    $response->assertOk();

    expect($response->body['broadcasts'])->toHaveCount(1)
        ->and($response->body['broadcasts'][0]['id'])->toBe($broadcastId)
        ->and($response->body['broadcasts'][0]['stash_id'])->toBe($stashId);
});

test('item exposes enriched metadata fields', function (): void {
    [$headers, , $mediaItemId] = $this->bootstrapFakeDownloadStash('metadata-demo');

    $response = $this->http->get("/api/v1/items/{$mediaItemId}", headers: $headers);
    $response->assertOk();

    expect($response->body['item'])->toHaveKeys([
        'description', 'upstream_state', 'content_type', 'creator_name', 'last_seen_upstream_at',
    ]);
});
