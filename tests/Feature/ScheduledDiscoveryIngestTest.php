<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Commands\CommandRecord;
use App\Commands\CommandType;
use App\Providers\Fake\FakeProvider;
use App\Providers\ProviderRegistry;
use App\Stashes\StashId;
use App\Stashes\StashInputRepository;
use App\Stashes\StashItemRecord;
use App\Stashes\SyncMode;
use App\System\Scheduler\RoutineDiscoveryScheduler;
use Tempest\Http\Status;

test('scheduled discovery ingests newly published items and stays idle when nothing changed', function (): void {
    $headers = $this->authHeaders();

    // Go through the real add-input flow so the stash input matches what
    // discovery resolves later -- a hand-built input would not.
    $stash = $this->http->post('/api/v1/stashes', ['name' => 'Scheduled Sync'], headers: $headers)
        ->assertStatus(Status::CREATED);
    $stashId = $stash->body['stash']['id'];

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/scheduled-sync'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $this->http->post('/api/v1/stashes/' . $stashId . '/inputs', [
        'preflight_command_id' => $preflight->body['command_id'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    expect(StashItemRecord::select()->where('stashId', $stashId)->all())->toHaveCount(3);

    $scheduler = $this->container->get(RoutineDiscoveryScheduler::class);
    $inputs = $this->container->get(StashInputRepository::class);

    $makeDue = function () use ($inputs, $stashId): void {
        foreach ($inputs->listForStash(StashId::parse($stashId)) as $input) {
            $input->syncMode = SyncMode::Automatic;
            $input->nextCheckAt = null;
            $inputs->save($input);
        }
    };

    $addInputCount = static fn (): int => count(
        CommandRecord::select()->where('type', CommandType::StashAddInput)->all(),
    );

    // Nothing new upstream: the scheduled preflight must not chain an ingest,
    // otherwise every stash would rebuild its broadcasts once an hour forever.
    $makeDue();
    expect($scheduler->runDueChecks())->toBe(1);
    $this->processAllJobs();

    expect($addInputCount())->toBe(1)
        ->and(StashItemRecord::select()->where('stashId', $stashId)->all())->toHaveCount(3);

    // A new episode appears upstream: the scheduled preflight must ingest it.
    $provider = $this->container->get(ProviderRegistry::class)->get('fake');
    assert($provider instanceof FakeProvider);
    $provider->advanceSyncGeneration('channel:scheduled-sync');

    $makeDue();
    expect($scheduler->runDueChecks())->toBe(1);
    $this->processAllJobs();

    expect($addInputCount())->toBe(2)
        ->and(StashItemRecord::select()->where('stashId', $stashId)->all())->toHaveCount(4);
});
