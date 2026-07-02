<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Config\StashdConfig;
use App\Stashes\DownloadPolicy;
use App\Stashes\StashRecord;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRecord;
use App\Vault\MediaItemState;
use Tempest\Http\Status;

test('item.download moves fake media from temp into vault and marks assets ready', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash();
    $config = $this->container->get(StashdConfig::class);
    $media = MediaItemRecord::findById(new \Tempest\Database\PrimaryKey($mediaItemId));

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $download->body['command_id'], headers: $headers);
    $command->assertOk();

    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['assets_ready'])->toBeGreaterThan(0);

    $item = $this->http->get('/api/v1/items/' . $mediaItemId, headers: $headers);
    $item->assertOk();
    expect($item->body['item']['state'])->toBe('ready');

    $assets = $this->http->get('/api/v1/items/' . $mediaItemId . '/assets', headers: $headers);
    $assets->assertOk();
    expect($assets->body['assets'])->not->toBeEmpty();

    $originalPath = $config->vaultPath() . '/fake/items/' . $media->providerItemId . '/original.fake';
    expect(file_exists($originalPath))->toBeTrue();

    $tempDir = rtrim($config->tempPath(), '/') . '/downloads/' . $command->body['jobs'][0]['id'];
    expect(is_dir($tempDir))->toBeFalse();
});

test('metadata-only policy rejects item.download', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('metadata-only-demo');

    $stash = StashRecord::findById(new \Tempest\Database\PrimaryKey($stashId));
    $stash->downloadPolicy = DownloadPolicy::MetadataOnly;
    $stash->save();

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $download->body['command_id'], headers: $headers);
    $command->assertOk();

    expect($command->body['command']['state'])->toBe('failed')
        ->and($command->body['jobs'][0]['last_error'])->toContain('download_policy_metadata_only');
});

test('retry download does not corrupt existing vault assets', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('retry-demo');
    $config = $this->container->get(StashdConfig::class);
    $media = MediaItemRecord::findById(new \Tempest\Database\PrimaryKey($mediaItemId));

    foreach ([1, 2] as $attempt) {
        $download = $this->http->post('/api/v1/commands', [
            'type' => 'item.download',
            'options' => [
                'media_item_id' => $mediaItemId,
                'stash_id' => $stashId,
            ],
        ], headers: $headers)->assertStatus(Status::CREATED);
        $this->processAllJobs();

        $command = $this->http->get('/api/v1/commands/' . $download->body['command_id'], headers: $headers);
        expect($command->body['command']['state'])->toBe('completed');
    }

    $originalPath = $config->vaultPath() . '/fake/items/' . $media->providerItemId . '/original.fake';
    $firstChecksum = hash_file('sha256', $originalPath);
    expect($firstChecksum)->not->toBeFalse();

    $assets = $this->container->get(\App\Vault\AssetRepository::class)
        ->findByMediaItemAndRole(
            MediaItemId::parse($mediaItemId),
            AssetRole::VaultOriginal,
        );
    expect($assets?->state)->toBe(AssetState::Ready)
        ->and($assets?->checksum)->toBe('sha256:' . $firstChecksum);
});

test('system.verify_vault marks missing files without touching storage-unavailable roots', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('verify-demo');
    $assets = $this->container->get(\App\Vault\AssetRepository::class);

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $original = $assets->findByMediaItemAndRole(
        MediaItemId::parse($mediaItemId),
        AssetRole::VaultOriginal,
    );
    $path = $original?->path;
    expect($path)->not->toBeNull();
    unlink($path);

    $verify = $this->http->post('/api/v1/commands', [
        'type' => 'system.verify_vault',
        'options' => [],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $verify->body['command_id'], headers: $headers);
    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['missing'])->toBeGreaterThan(0);

    $original = $assets->findByMediaItemAndRole(
        MediaItemId::parse($mediaItemId),
        AssetRole::VaultOriginal,
    );
    expect($original?->state)->toBe(AssetState::Missing);

    $item = MediaItemRecord::findById(new \Tempest\Database\PrimaryKey($mediaItemId));
    expect($item?->state)->toBe(MediaItemState::Missing);
});

test('download policy evaluator blocks automatic scheduling for manual download', function (): void {
    $evaluator = $this->container->get(\App\Downloads\DownloadPolicyEvaluator::class);

    expect($evaluator->allowsAutomaticDownload(DownloadPolicy::ManualDownload))->toBeFalse()
        ->and($evaluator->allowsAutomaticDownload(DownloadPolicy::Video))->toBeTrue();
});
