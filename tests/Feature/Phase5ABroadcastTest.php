<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Broadcasts\BroadcastFilenameBuilder;
use App\Broadcasts\BroadcastId;
use App\Broadcasts\BroadcastLifecycleService;
use App\Broadcasts\HardlinkPublisher;
use App\Config\StashdConfig;
use App\Stashes\StashId;
use App\Stashes\StashItemRecord;
use App\System\Activity\ActivityEventRecord;
use App\Vault\AssetRole;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRecord;
use Tempest\Http\Status;

test('create jellyfin broadcast for stash returns snake_case json', function (): void {
    [$headers, $stashId] = array_slice($this->bootstrapFakeDownloadStash('broadcast-create'), 0, 2);

    $response = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'jellyfin',
        'name' => 'Demo Series',
        'slug' => 'demo-series',
    ], headers: $headers)->assertStatus(Status::CREATED);

    expect($response->body)->toHaveKey('broadcast')
        ->and($response->body['broadcast']['type'])->toBe('jellyfin')
        ->and($response->body['broadcast']['state'])->toBe('pending')
        ->and($response->body['broadcast']['stash_id'])->toBe($stashId)
        ->and($response->body['broadcast']['last_planned_at'])->toBeNull();
});

test('previewing a jellyfin broadcast reports eligible items and their vault size, with nothing needing transcode', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('broadcast-preview-jellyfin');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $response = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts/preview', [
        'type' => 'jellyfin',
    ], headers: $headers)->assertOk();

    $preview = $response->body['preview'];

    expect($preview['eligible_item_count'])->toBe(1)
        ->and($preview['skipped_item_count'])->toBeGreaterThan(0)
        ->and($preview['vault_size_bytes'])->toBeGreaterThan(0)
        ->and($preview['hardlinked_item_count'])->toBe(1)
        ->and($preview['transcode_item_count'])->toBe(0);
});

test('previewing an audio podcast counts video-sourced episodes as needing transcode', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('broadcast-preview-podcast');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $response = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts/preview', [
        'type' => 'podcast',
        'mediaKind' => 'audio',
    ], headers: $headers)->assertOk();

    $preview = $response->body['preview'];

    // manual_download-policy fake downloads produce a video original, so an
    // audio podcast needs it transcoded.
    expect($preview['eligible_item_count'])->toBe(1)
        ->and($preview['transcode_item_count'])->toBe(1)
        ->and($preview['hardlinked_item_count'])->toBe(0);
});

test('previewing skips items that have not been downloaded yet', function (): void {
    [$headers, $stashId] = array_slice($this->bootstrapFakeDownloadStash('broadcast-preview-skipped'), 0, 2);

    $response = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts/preview', [
        'type' => 'jellyfin',
    ], headers: $headers)->assertOk();

    $preview = $response->body['preview'];

    expect($preview['eligible_item_count'])->toBe(0)
        ->and($preview['skipped_item_count'])->toBeGreaterThan(0)
        ->and($preview['vault_size_bytes'])->toBe(0);
});

test('previewing does not create a broadcast record', function (): void {
    [$headers, $stashId] = array_slice($this->bootstrapFakeDownloadStash('broadcast-preview-no-create'), 0, 2);

    $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts/preview', [
        'type' => 'jellyfin',
    ], headers: $headers)->assertOk();

    $broadcasts = $this->http->get('/api/v1/stashes/' . $stashId . '/broadcasts', headers: $headers)->assertOk();

    expect($broadcasts->body['broadcasts'])->toBeEmpty();
});

test('previewing an unsupported broadcast type is rejected', function (): void {
    [$headers, $stashId] = array_slice($this->bootstrapFakeDownloadStash('broadcast-preview-bad-type'), 0, 2);

    $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts/preview', [
        'type' => 'not_a_real_type',
    ], headers: $headers)->assertStatus(Status::BAD_REQUEST);
});

test('creating a broadcast without a name defaults to "{stash} {plugin label}"', function (): void {
    $headers = $this->authHeaders();

    $stash = $this->http->post('/api/v1/stashes', [
        'name' => 'My Channel',
    ], headers: $headers)->assertStatus(Status::CREATED);
    $stashId = $stash->body['stash']['id'];

    $response = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'jellyfin',
    ], headers: $headers)->assertStatus(Status::CREATED);

    expect($response->body['broadcast']['name'])->toBe('My Channel Jellyfin Series')
        ->and($response->body['broadcast']['slug'])->toBe('my-channel-jellyfin-series');
});

test('a second unnamed broadcast of the same type gets a deduped slug instead of erroring', function (): void {
    $headers = $this->authHeaders();

    $stash = $this->http->post('/api/v1/stashes', [
        'name' => 'My Channel',
    ], headers: $headers)->assertStatus(Status::CREATED);
    $stashId = $stash->body['stash']['id'];

    $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'jellyfin',
    ], headers: $headers)->assertStatus(Status::CREATED);

    $second = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'jellyfin',
    ], headers: $headers)->assertStatus(Status::CREATED);

    expect($second->body['broadcast']['name'])->toBe('My Channel Jellyfin Series')
        ->and($second->body['broadcast']['slug'])->toBe('my-channel-jellyfin-series-2');
});

test('a podcast broadcast can be created with no name and no settings at all', function (): void {
    [$headers, $stashId] = array_slice($this->bootstrapFakeDownloadStash('podcast-defaults'), 0, 2);

    $response = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'podcast',
    ], headers: $headers)->assertStatus(Status::CREATED);

    expect($response->body['broadcast']['name'])->toContain('Podcast');
});

test('broadcast.plan produces intended files without writing to disk', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-plan');

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    expect($this->http->get('/api/v1/commands/' . $download->body['command_id'], headers: $headers)->body['command']['state'])
        ->toBe('completed');

    $config = $this->container->get(StashdConfig::class);
    $planner = $this->container->get(BroadcastLifecycleService::class);
    $plan = $planner->plan(BroadcastId::parse($broadcastId));

    expect($plan->files)->toHaveCount(1)
        ->and($plan->files[0]->absolutePath)->toStartWith($config->broadcastsPath())
        ->and(is_file($plan->files[0]->absolutePath))->toBeFalse();

    $command = $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.plan',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $show = $this->http->get('/api/v1/commands/' . $command->body['command_id'], headers: $headers);
    expect($show->body['command']['state'])->toBe('completed')
        ->and($show->body['command']['result']['plan']['file_count'])->toBe(1);
});

test('broadcast.rebuild publishes hardlinks from vault originals', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-rebuild');

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $config = $this->container->get(StashdConfig::class);
    $media = MediaItemRecord::findById(new \Tempest\Database\PrimaryKey($mediaItemId));
    $vaultPath = $config->vaultPath() . '/fake/items/' . $media->providerItemId . '/original.fake';

    $rebuild = $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rebuild',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $rebuild->body['command_id'], headers: $headers);
    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['publish']['published_count'])->toBe(1)
        ->and($command->body['command']['result']['verify']['ok'])->toBeTrue();

    $broadcast = $this->http->get('/api/v1/broadcasts/' . $broadcastId, headers: $headers);
    expect($broadcast->body['broadcast']['state'])->toBe('ready');

    $items = $this->http->get('/api/v1/broadcasts/' . $broadcastId . '/items', headers: $headers);
    expect($items->body['items'][0]['state'])->toBe('ready');

    $publishedPath = $items->body['items'][0]['published_path'];
    expect(is_file($publishedPath))->toBeTrue()
        ->and(is_file($vaultPath))->toBeTrue();

    if (HardlinkPublisher::sameFile($vaultPath, $publishedPath)) {
        expect(HardlinkPublisher::sameFile($vaultPath, $publishedPath))->toBeTrue();
    } else {
        expect(filesize($vaultPath))->toBe(filesize($publishedPath));
        expect(file_get_contents($vaultPath))->toBe(file_get_contents($publishedPath));
    }
});

test('a broadcast\'s show/index response embeds live items and impact, not just the create-time preview', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-impact');

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

    $broadcast = $this->http->get('/api/v1/broadcasts/' . $broadcastId, headers: $headers)->assertOk()->body['broadcast'];

    expect($broadcast['items'])->toHaveCount(1)
        ->and($broadcast['items'][0]['state'])->toBe('ready')
        ->and($broadcast['impact']['eligible_item_count'])->toBe(1)
        ->and($broadcast['impact']['vault_size_bytes'])->toBeGreaterThan(0)
        ->and($broadcast['impact']['hardlinked_item_count'])->toBe(1)
        ->and($broadcast['impact']['transcode_item_count'])->toBe(0);

    $index = $this->http->get('/api/v1/stashes/' . $stashId . '/broadcasts', headers: $headers)->assertOk()->body['broadcasts'];
    expect($index[0]['impact'])->toBe($broadcast['impact']);
});

test('broadcast.verify marks missing generated files stale', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-verify-missing');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers);
    $this->processAllJobs();

    $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rebuild',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers);
    $this->processAllJobs();

    $items = $this->http->get('/api/v1/broadcasts/' . $broadcastId . '/items', headers: $headers);
    unlink($items->body['items'][0]['published_path']);

    $verify = $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.verify',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $verify->body['command_id'], headers: $headers);
    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['verify']['ok'])->toBeFalse()
        ->and($command->body['command']['result']['verify']['missing_item_ids'])->not->toBeEmpty();

    $itemsAfter = $this->http->get('/api/v1/broadcasts/' . $broadcastId . '/items', headers: $headers);
    expect($itemsAfter->body['items'][0]['state'])->toBe('stale');

    $broadcast = $this->http->get('/api/v1/broadcasts/' . $broadcastId, headers: $headers);
    expect($broadcast->body['broadcast']['state'])->toBe('stale');
});

test('broadcast.prune removes generated files only and never vault originals', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-prune');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers);
    $this->processAllJobs();

    $config = $this->container->get(StashdConfig::class);
    $media = MediaItemRecord::findById(new \Tempest\Database\PrimaryKey($mediaItemId));
    $vaultPath = $config->vaultPath() . '/fake/items/' . $media->providerItemId . '/original.fake';

    $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rebuild',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers);
    $this->processAllJobs();

    $items = $this->http->get('/api/v1/broadcasts/' . $broadcastId . '/items', headers: $headers);
    $publishedPath = $items->body['items'][0]['published_path'];
    expect(is_file($publishedPath))->toBeTrue();

    $root = $config->broadcastsPath() . '/' . $broadcastId;
    file_put_contents($root . '/stale-extra.txt', 'stale');

    $prune = $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.prune',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $prune->body['command_id'], headers: $headers);
    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['prune']['removed_count'])->toBeGreaterThan(0)
        ->and(is_file($root . '/stale-extra.txt'))->toBeFalse()
        ->and(is_file($vaultPath))->toBeTrue()
        ->and(is_file($publishedPath))->toBeTrue();
});

test('hardlink unavailable returns stable broadcast_hardlink_unavailable error', function (): void {
    putenv('STASHD_BROADCAST_HARDLINK_FORCE_FAIL=1');
    $_ENV['STASHD_BROADCAST_HARDLINK_FORCE_FAIL'] = '1';

    try {
        [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-hardlink-fail');

        $this->http->post('/api/v1/commands', [
            'type' => 'item.download',
            'options' => [
                'media_item_id' => $mediaItemId,
                'stash_id' => $stashId,
            ],
        ], headers: $headers);
        $this->processAllJobs();

        $rebuild = $this->http->post('/api/v1/commands', [
            'type' => 'broadcast.rebuild',
            'options' => ['broadcast_id' => $broadcastId],
        ], headers: $headers)->assertStatus(Status::CREATED);
        $this->processAllJobs();

        $command = $this->http->get('/api/v1/commands/' . $rebuild->body['command_id'], headers: $headers);
        expect($command->body['command']['state'])->toBe('failed')
            ->and($command->body['jobs'][0]['last_error'])->toContain('broadcast_hardlink_unavailable');
    } finally {
        putenv('STASHD_BROADCAST_HARDLINK_FORCE_FAIL');
        unset($_ENV['STASHD_BROADCAST_HARDLINK_FORCE_FAIL']);
    }
});

test('broadcast rebuild is idempotent', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-idempotent');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers);
    $this->processAllJobs();

    foreach ([1, 2] as $attempt) {
        $rebuild = $this->http->post('/api/v1/commands', [
            'type' => 'broadcast.rebuild',
            'options' => ['broadcast_id' => $broadcastId],
        ], headers: $headers)->assertStatus(Status::CREATED);
        $this->processAllJobs();

        $command = $this->http->get('/api/v1/commands/' . $rebuild->body['command_id'], headers: $headers);
        expect($command->body['command']['state'])->toBe('completed');
    }

    $items = $this->http->get('/api/v1/broadcasts/' . $broadcastId . '/items', headers: $headers);
    expect($items->body['items'][0]['state'])->toBe('ready');
});

test('broadcast commands emit activity events', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-activity');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers);
    $this->processAllJobs();

    $rebuild = $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rebuild',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $types = array_map(
        static fn (ActivityEventRecord $event): string => $event->type,
        ActivityEventRecord::select()->orderBy('createdAt', \Tempest\Database\Direction::DESC)->limit(20)->all(),
    );

    expect($types)->toContain('broadcast.rebuild_started')
        ->and($types)->toContain('broadcast.published')
        ->and($types)->toContain('broadcast.verified');
});

test('broadcast filename builder rejects path traversal segments', function (): void {
    $builder = new BroadcastFilenameBuilder();
    $stashItem = new StashItemRecord(
        stashId: StashId::parse('stash_01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        mediaItemId: MediaItemId::parse('media_01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        state: \App\Stashes\StashItemState::Active,
        displayTitle: '../../etc/passwd',
    );
    $mediaItem = new MediaItemRecord(
        providerKey: 'fake',
        providerItemId: 'item-1',
        canonicalUri: 'fake://item/1',
        title: '../secret',
        state: \App\Vault\MediaItemState::Ready,
        upstreamState: \App\Vault\UpstreamState::Available,
    );

    $filename = $builder->episodeFilename($stashItem, $mediaItem, '/vault/original.fake', 1);

    expect($filename)->not->toContain('..')
        ->and($filename)->not->toContain('/');
});

test('broadcast hardlink assets are recorded with derived vault source', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('broadcast-assets');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers);
    $this->processAllJobs();

    $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rebuild',
        'options' => ['broadcast_id' => $broadcastId],
    ], headers: $headers);
    $this->processAllJobs();

    $hardlink = \App\Vault\AssetRecord::select()
        ->where('broadcastId = ? AND role = ?', $broadcastId, AssetRole::Hardlink)
        ->first();

    expect($hardlink)->not->toBeNull()
        ->and($hardlink->derivedFromAssetId)->not->toBeNull()
        ->and($hardlink->state->value)->toBe('ready');
});
