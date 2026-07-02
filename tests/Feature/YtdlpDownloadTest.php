<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Config\StashdConfig;
use App\Downloads\Ytdlp\StubYtdlpGateway;
use App\Downloads\Ytdlp\YtdlpGateway;
use App\Stashes\DownloadPolicy;
use App\Stashes\StashRecord;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRecord;
use Tempest\Http\Status;

beforeEach(function (): void {
    putenv('STASHD_REAL_DOWNLOADS_ENABLED=1');
    $_ENV['STASHD_REAL_DOWNLOADS_ENABLED'] = '1';

    $gateway = $this->container->get(YtdlpGateway::class);
    assert($gateway instanceof StubYtdlpGateway);
    $gateway->downloadCalls = 0;
    $gateway->extractInfoCalls = 0;
    $gateway->failNextDownload = false;
});

afterEach(function (): void {
    putenv('STASHD_REAL_DOWNLOADS_ENABLED');
    unset($_ENV['STASHD_REAL_DOWNLOADS_ENABLED']);
});

test('youtube item.download uses ytdlp stub and ingests into vault when enabled', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapYouTubeDownloadStash();
    $config = $this->container->get(StashdConfig::class);
    $media = MediaItemRecord::findById(new \Tempest\Database\PrimaryKey($mediaItemId));
    $gateway = $this->container->get(YtdlpGateway::class);
    assert($gateway instanceof StubYtdlpGateway);

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    expect($gateway->downloadCalls)->toBe(1);

    $command = $this->http->get('/api/v1/commands/' . $download->body['command_id'], headers: $headers);
    expect($command->body['command']['state'])->toBe('completed');

    $originalPath = $config->vaultPath() . '/youtube/items/' . $media->providerItemId . '/original.mp4';
    expect(file_exists($originalPath))->toBeTrue();

    $source = json_decode((string) file_get_contents(dirname($originalPath) . '/source.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($source['downloader']['implementation'])->toBe('ytdlphp')
        ->and($source['result']['ytdlp_binary'])->toBe('stub-yt-dlp');

    $assets = $this->container->get(\App\Vault\AssetRepository::class)
        ->findByMediaItemAndRole(
            MediaItemId::parse($mediaItemId),
            AssetRole::VaultOriginal,
        );
    expect($assets?->state)->toBe(AssetState::Ready)
        ->and($assets?->checksum)->toStartWith('sha256:');
});

test('youtube item.download fails when real downloads are disabled', function (): void {
    putenv('STASHD_REAL_DOWNLOADS_ENABLED=0');
    $_ENV['STASHD_REAL_DOWNLOADS_ENABLED'] = '0';

    [$headers, $stashId, $mediaItemId] = $this->bootstrapYouTubeDownloadStash();

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $download->body['command_id'], headers: $headers);
    expect($command->body['command']['state'])->toBe('failed')
        ->and($command->body['jobs'][0]['last_error'])->toContain('download_ytdlp_unavailable');
});

test('metadata-only youtube download does not invoke ytdlp gateway', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapYouTubeDownloadStash();
    $gateway = $this->container->get(YtdlpGateway::class);
    assert($gateway instanceof StubYtdlpGateway);

    $stash = StashRecord::findById(new \Tempest\Database\PrimaryKey($stashId));
    $stash->downloadPolicy = DownloadPolicy::MetadataOnly;
    $stash->save();

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    expect($gateway->downloadCalls)->toBe(0)
        ->and($gateway->extractInfoCalls)->toBe(0);
});

test('ytdlp download failure leaves no ready vault original', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapYouTubeDownloadStash();
    $gateway = $this->container->get(YtdlpGateway::class);
    assert($gateway instanceof StubYtdlpGateway);
    $gateway->failNextDownload = true;

    $download = $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => [
            'media_item_id' => $mediaItemId,
            'stash_id' => $stashId,
        ],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $command = $this->http->get('/api/v1/commands/' . $download->body['command_id'], headers: $headers);
    expect($command->body['command']['state'])->toBe('failed');

    $assets = $this->container->get(\App\Vault\AssetRepository::class)
        ->findByMediaItemAndRole(
            MediaItemId::parse($mediaItemId),
            AssetRole::VaultOriginal,
        );

    expect($assets?->state ?? null)->not->toBe(AssetState::Ready);
});
