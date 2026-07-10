<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Config\StashdConfig;
use App\Console\RebuildCommand;
use App\Vault\MediaItemRecord;
use Tempest\Console\Console;
use Tempest\Console\ExitCode;
use Tempest\Core\Environment;
use Tempest\Database\PrimaryKey;
use Tempest\Http\Status;

test('stashd:rebuild deletes vault and broadcast files from disk, not just the database', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('rebuild-wipe');
    $config = $this->container->get(StashdConfig::class);
    $media = MediaItemRecord::findById(new PrimaryKey($mediaItemId));

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $vaultFile = $config->vaultPath() . '/fake/items/' . $media->providerItemId . '/original.fake';
    expect(file_exists($vaultFile))->toBeTrue();

    $broadcast = $this->http->post('/api/v1/stashes/' . $stashId . '/broadcasts', [
        'type' => 'jellyfin',
        'name' => 'Rebuild Wipe Show',
        'slug' => 'rebuild-wipe-' . bin2hex(random_bytes(3)),
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->http->post('/api/v1/commands', [
        'type' => 'broadcast.rebuild',
        'options' => ['broadcast_id' => $broadcast->body['broadcast']['id']],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $broadcastRoot = $config->broadcastsPath() . '/jellyfin/Rebuild Wipe Show';
    expect(is_dir($broadcastRoot))->toBeTrue();

    $command = $this->container->get(RebuildCommand::class);
    expect($command())->toBe(ExitCode::SUCCESS);

    expect(file_exists($vaultFile))->toBeFalse()
        ->and(is_dir($broadcastRoot))->toBeFalse()
        ->and(is_dir($config->vaultPath()))->toBeTrue()
        ->and(is_dir($config->broadcastsPath()))->toBeTrue();
});

test('stashd:rebuild in a caution-requiring environment refuses to wipe files without confirmation', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('rebuild-wipe-refused');
    $config = $this->container->get(StashdConfig::class);
    $media = MediaItemRecord::findById(new PrimaryKey($mediaItemId));

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $vaultFile = $config->vaultPath() . '/fake/items/' . $media->providerItemId . '/original.fake';
    expect(file_exists($vaultFile))->toBeTrue();

    $this->container->singleton(Environment::class, Environment::PRODUCTION);
    $this->container->get(Console::class)->disablePrompting();

    $command = $this->container->get(RebuildCommand::class);
    expect($command())->toBe(ExitCode::CANCELLED);

    expect(file_exists($vaultFile))->toBeTrue();
});
