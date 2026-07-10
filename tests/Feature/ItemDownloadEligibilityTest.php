<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Stashes\StashId;
use App\Stashes\StashItemRepository;
use App\Stashes\StashItemState;
use App\Stashes\StashRepository;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;
use App\Vault\MediaItemState;
use Tempest\Http\Status;

test('item.download is rejected for an ignored stash item, surfacing the ignored reason', function (): void {
    $headers = $this->authHeaders();

    $stashes = $this->container->get(StashRepository::class);
    $items = $this->container->get(StashItemRepository::class);
    $media = $this->container->get(MediaItemRepository::class);

    $stash = $stashes->create(name: 'Eligibility Spike', slug: 'eligibility-spike-' . bin2hex(random_bytes(3)));
    $stashId = StashId::fromPrimaryKey($stash->id);

    $mediaItem = $media->create(
        providerKey: 'fake',
        providerItemId: 'eligibility-premiere',
        canonicalUri: 'fake://item/eligibility-premiere',
        title: 'Unaired Premiere',
        state: MediaItemState::Discovered,
        contentType: 'premiere',
    );
    $items->create(
        stashId: $stashId,
        mediaItemId: MediaItemId::fromPrimaryKey($mediaItem->id),
        state: StashItemState::Ignored,
        ignoredReason: 'filter_video_type',
    );

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => (string) $mediaItem->id,
            'stash_id' => $stashId->toString(),
        ],
    ], headers: $headers);

    $response->assertStatus(Status::BAD_REQUEST);
    expect($response->body['error']['message'])->toContain('filter_video_type');
});

test('item.download is unaffected for an active stash item', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('eligibility-active-demo');

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers);

    $response->assertStatus(Status::CREATED);
});
