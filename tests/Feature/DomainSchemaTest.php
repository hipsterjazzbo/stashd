<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Broadcasts\BroadcastRecord;
use App\Stashes\StashInputRecord;
use App\Stashes\StashItemRecord;
use App\Stashes\StashRecord;
use App\Vault\MediaItemRecord;
use Tempest\Database\Database;
use Tempest\Database\Query;

test('domain schema migration creates all v1 tables on a fresh database', function (): void {
    $database = $this->container->get(Database::class);

    $tables = [
        'stashes',
        'stash_inputs',
        'stash_items',
        'media_items',
        'media_item_sources',
        'raw_metadata_snapshots',
        'assets',
        'broadcasts',
        'broadcast_items',
        'broadcast_triggers',
        'broadcast_trigger_runs',
        'provider_accounts',
        'media_server_connections',
        'users',
        'api_tokens',
        'secrets',
    ];

    foreach ($tables as $table) {
        expect(schemaTableExists($database, $table))->toBeTrue("Expected table {$table} to exist.");
    }

    $jobsInfo = $database->fetch(new Query('PRAGMA table_info(jobs)'));
    $jobColumns = array_column($jobsInfo ?? [], 'name');

    expect($jobColumns)->toContain('progressRate')
        ->and($jobColumns)->toContain('progressEtaSeconds');
});

test('media item provider identity is unique', function (): void {
    $repo = $this->container->get(\App\Vault\MediaItemRepository::class);

    $repo->create(
        providerKey: 'fake',
        providerItemId: 'dup-item',
        canonicalUri: 'fake://item/dup-item',
        title: 'First',
    );

    expect(fn () => $repo->create(
        providerKey: 'fake',
        providerItemId: 'dup-item',
        canonicalUri: 'fake://item/dup-item-2',
        title: 'Duplicate',
    ))->toThrow(\Tempest\Database\Exceptions\QueryWasInvalid::class);
});

test('stash item enforces stash and media item relationship uniqueness', function (): void {
    $stashes = $this->container->get(\App\Stashes\StashRepository::class);
    $media = $this->container->get(\App\Vault\MediaItemRepository::class);
    $items = $this->container->get(\App\Stashes\StashItemRepository::class);

    $stash = $stashes->create('Test Stash', 'test-stash');
    $mediaItem = $media->create('fake', 'rel-item', 'fake://item/rel-item', 'Rel Item');

    $items->create(
        stashId: \App\Support\PrefixedUlid::parse((string) $stash->id),
        mediaItemId: \App\Support\PrefixedUlid::parse((string) $mediaItem->id),
    );

    expect(fn () => $items->create(
        stashId: \App\Support\PrefixedUlid::parse((string) $stash->id),
        mediaItemId: \App\Support\PrefixedUlid::parse((string) $mediaItem->id),
    ))->toThrow(\Tempest\Database\Exceptions\QueryWasInvalid::class);
});

test('job requires a valid command foreign key', function (): void {
    $jobs = $this->container->get(\App\Jobs\JobRepository::class);

    expect(fn () => $jobs->create(
        intent: \App\Jobs\JobIntent::Preflight,
        commandId: \App\Support\PrefixedUlid::parse('cmd_01ARZ3NDEKTSV4RRFFQ69G5FAV'),
    ))->toThrow(\Tempest\Database\Exceptions\QueryWasInvalid::class);
});

test('broadcast belongs to stash via foreign key', function (): void {
    $stashes = $this->container->get(\App\Stashes\StashRepository::class);
    $broadcasts = $this->container->get(\App\Broadcasts\BroadcastRepository::class);

    $stash = $stashes->create('Broadcast Stash', 'broadcast-stash');
    $broadcast = $broadcasts->create(
        stashId: \App\Support\PrefixedUlid::parse((string) $stash->id),
        type: 'podcast',
        name: 'Podcast',
        slug: 'podcast',
    );

    expect($broadcast->stashId)->toBe((string) $stash->id);

    expect(fn () => $broadcasts->create(
        stashId: \App\Support\PrefixedUlid::parse('stash_01ARZ3NDEKTSV4RRFFQ69G5FAV'),
        type: 'podcast',
        name: 'Orphan',
        slug: 'orphan',
    ))->toThrow(\Tempest\Database\Exceptions\QueryWasInvalid::class);
});

test('repository smoke creates stash with input media item stash item and broadcast', function (): void {
    $stashes = $this->container->get(\App\Stashes\StashRepository::class);
    $inputs = $this->container->get(\App\Stashes\StashInputRepository::class);
    $media = $this->container->get(\App\Vault\MediaItemRepository::class);
    $items = $this->container->get(\App\Stashes\StashItemRepository::class);
    $broadcasts = $this->container->get(\App\Broadcasts\BroadcastRepository::class);

    $stash = $stashes->create('Demo', 'demo-stash');
    $stashId = \App\Support\PrefixedUlid::parse((string) $stash->id);

    $input = $inputs->create(
        stashId: $stashId,
        providerKey: 'fake',
        inputType: \App\Stashes\StashInputType::Channel,
        sourceUri: 'fake://channel/demo',
        providerInputId: 'channel:demo',
        title: 'Demo Channel',
    );

    $mediaItem = $media->create(
        providerKey: 'fake',
        providerItemId: 'demo-episode-1',
        canonicalUri: 'fake://item/demo-episode-1',
        title: 'Episode 1',
        durationSeconds: 600,
    );

    $stashItem = $items->create(
        stashId: $stashId,
        mediaItemId: \App\Support\PrefixedUlid::parse((string) $mediaItem->id),
        stashInputId: \App\Support\PrefixedUlid::parse((string) $input->id),
        position: 1,
    );

    $broadcast = $broadcasts->create(
        stashId: $stashId,
        type: 'jellyfin',
        name: 'Demo Series',
        slug: 'demo-series',
    );

    expect(StashRecord::findById($stash->id))->not->toBeNull()
        ->and(StashInputRecord::findById($input->id))->not->toBeNull()
        ->and(MediaItemRecord::findById($mediaItem->id))->not->toBeNull()
        ->and(StashItemRecord::findById($stashItem->id))->not->toBeNull()
        ->and(BroadcastRecord::findById($broadcast->id))->not->toBeNull()
        ->and($stashItem->stashId)->toBe((string) $stash->id)
        ->and($broadcast->stashId)->toBe((string) $stash->id);
});

function schemaTableExists(Database $database, string $table): bool
{
    $row = $database->fetchFirst(new Query(
        "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
        bindings: [$table],
    ));

    return $row !== null;
}
