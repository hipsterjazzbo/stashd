<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Broadcasts\BroadcastId;
use App\Broadcasts\BroadcastItemRecord;
use App\Broadcasts\BroadcastPathBuilder;
use App\Broadcasts\BroadcastRepository;
use App\Config\StashdConfig;
use App\Vault\AssetRepository;
use Tempest\Http\Status;

test('deleting a broadcast removes its generated output and records but preserves Vault media', function (): void {
    [$headers, $stashId, $mediaItemId, $broadcastId] = $this->bootstrapFakeDownloadBroadcast('delete-broadcast');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $broadcasts = $this->container->get(BroadcastRepository::class);
    $broadcast = $broadcasts->find(BroadcastId::parse($broadcastId));
    $root = $this->container->get(BroadcastPathBuilder::class)->broadcastRoot($broadcast);
    $vaultOriginal = $this->container->get(AssetRepository::class)->readyVaultOriginalsByMediaItem([$mediaItemId])[$mediaItemId];

    expect(is_dir($root))->toBeTrue()
        ->and(is_file($vaultOriginal->path))->toBeTrue()
        ->and(BroadcastItemRecord::select()->where('broadcastId', $broadcastId)->all())->not->toBeEmpty();

    $response = $this->http->delete('/api/v1/broadcasts/' . $broadcastId, headers: $headers)
        ->assertStatus(Status::ACCEPTED);

    expect($broadcasts->find(BroadcastId::parse($broadcastId)))->not->toBeNull();

    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $response->body['command_id'], headers: $headers)->assertOk();

    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['delete']['deleted'])->toBeTrue()
        ->and($command->body['command']['result']['delete']['removed_count'])->toBeGreaterThan(0)
        ->and($broadcasts->find(BroadcastId::parse($broadcastId)))->toBeNull()
        ->and(BroadcastItemRecord::select()->where('broadcastId', $broadcastId)->all())->toBeEmpty()
        ->and(is_dir($root))->toBeFalse()
        ->and(is_file($vaultOriginal->path))->toBeTrue();

    $this->http->get('/api/v1/broadcasts/' . $broadcastId, headers: $headers)->assertStatus(Status::NOT_FOUND);
});

test('deleting a custom-destination broadcast preserves siblings in the parent directory', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('delete-custom-broadcast');
    $config = $this->container->get(StashdConfig::class);
    $destination = $config->mediaPath . '/delete-custom-' . bin2hex(random_bytes(3));

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    mkdir($destination, 0775, true);
    file_put_contents($destination . '/keep.txt', 'keep me');

    $created = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'jellyfin',
        'name' => 'Custom Delete Show',
        'settings' => ['destination_path' => $destination],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $broadcastId = $created->body['broadcast']['id'];
    $this->processAllJobs();

    $root = $destination . '/Custom Delete Show';
    expect(is_dir($root))->toBeTrue()->and(is_file($destination . '/keep.txt'))->toBeTrue();

    $this->http->delete('/api/v1/broadcasts/' . $broadcastId, headers: $headers)->assertStatus(Status::ACCEPTED);
    $this->processAllJobs();

    expect(is_dir($root))->toBeFalse()
        ->and(is_file($destination . '/keep.txt'))->toBeTrue()
        ->and(file_get_contents($destination . '/keep.txt'))->toBe('keep me');
});

test('deleting refuses an unmarked destination directory and leaves the broadcast intact', function (): void {
    [$headers, $stashId] = array_slice($this->bootstrapFakeDownloadStash('delete-foreign-broadcast'), 0, 2);

    $created = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'jellyfin',
        'name' => 'Foreign Delete Show',
    ], headers: $headers)->assertStatus(Status::CREATED);
    $broadcastId = $created->body['broadcast']['id'];
    $broadcasts = $this->container->get(BroadcastRepository::class);
    $broadcast = $broadcasts->find(BroadcastId::parse($broadcastId));
    $root = $this->container->get(BroadcastPathBuilder::class)->broadcastRoot($broadcast);

    mkdir($root, 0775, true);
    file_put_contents($root . '/foreign.txt', 'not ours');

    $response = $this->http->delete('/api/v1/broadcasts/' . $broadcastId, headers: $headers)
        ->assertStatus(Status::ACCEPTED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $response->body['command_id'], headers: $headers)->assertOk();

    expect($command->body['command']['state'])->toBe('failed')
        ->and($command->body['jobs'][0]['last_error'])->toContain('broadcast_destination_conflict')
        ->and($broadcasts->find(BroadcastId::parse($broadcastId)))->not->toBeNull()
        ->and(is_file($root . '/foreign.txt'))->toBeTrue();
});

test('deleting an unknown broadcast returns not found', function (): void {
    $this->http->delete('/api/v1/broadcasts/broadcast_unknown', headers: $this->authHeaders())
        ->assertStatus(Status::NOT_FOUND);
});
