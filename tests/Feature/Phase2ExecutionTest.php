<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\AuthContext;
use App\Auth\AuthService;
use App\Commands\CommandType;
use App\Jobs\JobIntent;
use App\Jobs\JobRecord;
use App\Jobs\JobState;
use App\Jobs\JobWorkerService;
use App\System\Activity\ActivityEventRecord;
use App\System\Event\EventNotificationRecord;
use App\System\Event\EventPublisher;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;
use Tempest\Http\Status;

test('commands api dispatches stash preflight command', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [
            'source_uri' => 'fake://channel/commands-demo',
            'source_title' => 'Commands Demo',
            'origin' => 'browser_extension',
        ],
    ], headers: $headers);

    $response->assertStatus(Status::CREATED);
    expect($response->body['command_type'])->toBe('stash.preflight')
        ->and($response->body['command_state'])->toBe('accepted')
        ->and($response->body['job_ids'])->toHaveCount(1);

    $show = $this->http->get('/api/v1/commands/' . $response->body['command_id'], headers: $headers);
    $show->assertOk();
    expect($show->body['command']['type'])->toBe('stash.preflight')
        ->and($show->body['command']['state'])->toBe('accepted');
});

test('commands api rejects invalid payload', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [],
    ], headers: $headers);

    $response->assertStatus(Status::BAD_REQUEST);
    expect($response->body['error']['code'])->toBe('validation_error');
});

test('commands api rejects unsupported type', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'unknown.command',
        'options' => [],
    ], headers: $headers);

    $response->assertStatus(Status::BAD_REQUEST);
});

test('system storage check command creates and completes storage job', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'system.storage_check',
        'options' => [],
    ], headers: $headers);

    $response->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $job = $this->http->get('/api/v1/jobs/' . $response->body['job_ids'][0], headers: $headers);
    $job->assertOk();
    expect($job->body['job']['intent'])->toBe('storage_check')
        ->and($job->body['job']['state'])->toBe('ready');

    $command = $this->http->get('/api/v1/commands/' . $response->body['command_id'], headers: $headers);
    $command->assertOk();
    expect($command->body['command']['state'])->toBe('completed')
        ->and($command->body['command']['result']['status'])->not->toBeNull();
});

test('jobs api lists recent jobs', function (): void {
    $headers = $this->authHeaders();

    $this->http->post('/api/v1/commands', [
        'type' => 'system.storage_check',
        'options' => [],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $jobs = $this->http->get('/api/v1/jobs', headers: $headers);
    $jobs->assertOk();
    expect($jobs->body['jobs'])->not->toBeEmpty();
});

test('jobs api still surfaces the actively processing job when it is older than the 50 most recent', function (): void {
    $headers = $this->authHeaders();
    $jobs = $this->container->get(\App\Jobs\JobRepository::class);
    $transitions = $this->container->get(\App\System\State\StateTransitionService::class);

    $oldest = $jobs->create(intent: JobIntent::Enrich, entityType: 'media_item', entityId: null);
    $transitions->transitionJob($oldest, JobState::Processing);

    for ($i = 0; $i < 55; $i++) {
        $jobs->create(intent: JobIntent::Enrich, entityType: 'media_item', entityId: null);
    }

    $response = $this->http->get('/api/v1/jobs', headers: $headers)->assertOk();

    $ids = array_column($response->body['jobs'], 'id');
    expect($ids)->toContain((string) $oldest->id)
        ->and($response->body['jobs'][array_search((string) $oldest->id, $ids, true)]['state'])->toBe('processing');
});

test('jobs api exposes entity_type and entity_id for a media item download job', function (): void {
    [$headers, $stashId, $mediaItemId] = $this->bootstrapFakeDownloadStash('jobs-entity-link');

    $this->http->post('/api/v1/commands', [
        'type' => 'item.download',
        'options' => ['media_item_id' => $mediaItemId, 'stash_id' => $stashId],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $jobs = $this->http->get('/api/v1/jobs', headers: $headers)->assertOk();

    $downloadJobs = array_values(array_filter(
        $jobs->body['jobs'],
        static fn (array $job): bool => $job['entity_type'] === 'media_item' && $job['entity_id'] === $mediaItemId,
    ));

    expect($downloadJobs)->not->toBeEmpty();

    $this->processAllJobs();
});

test('job worker records failure with last error', function (): void {
    $headers = $this->authHeaders();
    $jobs = $this->container->get(\App\Jobs\JobRepository::class);

    $job = $jobs->create(
        intent: JobIntent::Enrich,
        entityType: 'test',
    );

    $worker = $this->container->get(JobWorkerService::class);
    expect($worker->processNextJob())->toBeTrue();

    $job = JobRecord::findById($job->id);
    expect($job->state)->toBe(JobState::Failed)
        ->and($job->lastError)->toContain('No handler registered');
});

test('stale processing jobs are recovered or failed based on attempts', function (): void {
    $headers = $this->authHeaders();

    $created = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/stale-demo'],
    ], headers: $headers);

    $job = JobRecord::findById(new \Tempest\Database\PrimaryKey($created->body['job_ids'][0]));
    $job->state = JobState::Processing;
    $job->attempts = 1;
    $job->maxAttempts = 3;
    $job->heartbeatAt = DateTime::now(Timezone::UTC)->minusSeconds(300);
    $job->startedAt = DateTime::now(Timezone::UTC);
    $job->save();

    $worker = $this->container->get(JobWorkerService::class);
    expect($worker->recoverStaleJobs())->toBe(1);

    $job = JobRecord::findById($job->id);
    expect($job->state)->toBe(JobState::Pending)
        ->and($job->lastError)->toContain('stalled');

    $job->state = JobState::Processing;
    $job->attempts = 3;
    $job->heartbeatAt = DateTime::now(Timezone::UTC)->minusSeconds(300);
    $job->save();

    expect($worker->recoverStaleJobs())->toBe(1);
    $job = JobRecord::findById($job->id);
    expect($job->state)->toBe(JobState::Failed);
});

test('command dispatch writes activity and notification events', function (): void {
    $headers = $this->authHeaders();

    $this->http->post('/api/v1/commands', [
        'type' => 'system.storage_check',
        'options' => [],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $activities = ActivityEventRecord::select()->all();
    expect($activities)->not->toBeEmpty();

    $types = array_map(static fn ($event): string => $event->type, $activities);
    expect($types)->toContain('command.accepted')
        ->and($types)->toContain('job.started')
        ->and($types)->toContain('storage_check.completed')
        ->and($types)->toContain('command.completed');

    $notifications = EventNotificationRecord::select()->all();
    expect($notifications)->not->toBeEmpty();
});

test('event publisher writes sse notification rows', function (): void {
    $publisher = $this->container->get(EventPublisher::class);
    $job = JobRecord::select()->first();

    if ($job === null) {
        $headers = $this->authHeaders();
        $created = $this->http->post('/api/v1/commands', [
            'type' => 'system.storage_check',
            'options' => [],
        ], headers: $headers);
        $job = JobRecord::findById(new \Tempest\Database\PrimaryKey($created->body['job_ids'][0]));
    }

    $publisher->jobProgress($job);

    $notification = EventNotificationRecord::select()
        ->where('eventType = ?', 'job.progress')
        ->orderBy('createdAt', \Tempest\Database\Direction::DESC)
        ->first();

    expect($notification)->not->toBeNull();
});

test('events endpoint requires authentication', function (): void {
    $users = $this->container->get(\App\Auth\UserRepository::class);
    $users->createAdmin(
        email: 'owner@stashd.test',
        passwordHash: password_hash('secret-password', PASSWORD_DEFAULT),
    );

    $this->http->get('/api/v1/events')->assertStatus(Status::UNAUTHORIZED);
});

test('request auth context does not leak between http requests', function (): void {
    $headers = $this->authHeaders();

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertOk();

    $context = $this->container->get(AuthContext::class);
    expect($context->user())->toBeNull();

    $this->http->get('/api/v1/auth/me')->assertStatus(Status::UNAUTHORIZED);
});

test('bearer auth does not leak to subsequent unauthenticated requests', function (): void {
    $headers = $this->authHeaders();

    $this->http->get('/api/v1/jobs', headers: $headers)->assertOk();
    $this->http->get('/api/v1/jobs')->assertStatus(Status::UNAUTHORIZED);
});

test('api token uses stashd_pat prefix and supports lookup and revoke', function (): void {
    $users = $this->container->get(\App\Auth\UserRepository::class);
    $auth = $this->container->get(AuthService::class);
    $user = $users->createAdmin(
        email: 'owner@stashd.test',
        passwordHash: password_hash('secret-password', PASSWORD_DEFAULT),
    );

    $created = $auth->createApiToken($user, 'phase2-token');
    expect($created['token'])->toStartWith('stashd_pat_')
        ->and($created['token_preview'])->toStartWith('stashd_pat_');

    $headers = ['Authorization' => 'Bearer ' . $created['token']];
    $this->http->get('/api/v1/auth/me', headers: $headers)->assertOk();

    $auth->revokeApiToken($user, \App\Auth\ApiTokenId::parse($created['id']));
    $this->http->get('/api/v1/auth/me', headers: $headers)->assertStatus(Status::UNAUTHORIZED);
});

test('scheduler creates preflight commands for due automatic stash inputs', function (): void {
    $stashRepo = $this->container->get(\App\Stashes\StashRepository::class);
    $inputRepo = $this->container->get(\App\Stashes\StashInputRepository::class);
    $scheduler = $this->container->get(\App\System\Scheduler\RoutineDiscoveryScheduler::class);

    $stash = $stashRepo->create('Scheduler Stash', 'scheduler-stash');
    $inputRepo->create(
        stashId: \App\Stashes\StashId::parse((string) $stash->id),
        providerKey: 'fake',
        inputType: \App\Stashes\StashInputType::Channel,
        sourceUri: 'fake://channel/scheduler-demo',
        providerInputId: 'scheduler-demo',
        title: 'Scheduler Channel',
        syncMode: \App\Stashes\SyncMode::Automatic,
    );

    expect($scheduler->runDueChecks())->toBe(1);

    $command = \App\Commands\CommandRecord::select()
        ->where('type = ?', CommandType::StashPreflight)
        ->orderBy('createdAt', \Tempest\Database\Direction::DESC)
        ->first();

    expect($command)->not->toBeNull();
});
