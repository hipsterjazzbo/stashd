<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\AuthService;
use App\Jobs\JobRecord;
use App\Jobs\JobState;
use App\System\Event\EventNotificationRecord;
use App\System\Event\EventPublisher;
use Tempest\Http\Responses\EventStream;
use Tempest\Http\Status;

test('post commands returns snake_case response keys', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/snake-case'],
    ], headers: $headers);

    $response->assertStatus(Status::CREATED);
    expect(array_keys($response->body))->toContain('command_id', 'command_state', 'job_ids', 'review_url')
        ->and(array_keys($response->body))->not->toContain('commandId', 'jobIds');
});

test('get command includes jobs and result after worker completion', function (): void {
    $headers = $this->authHeaders();

    $created = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/show-command'],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $show = $this->http->get('/api/v1/commands/' . $created->body['command_id'], headers: $headers);
    $show->assertOk();
    expect($show->body['command']['state'])->toBe('completed')
        ->and($show->body['jobs'])->toHaveCount(1)
        ->and($show->body['command']['result']['discovery']['estimated_item_count'])->toBe(3);
});

test('get job returns progress heartbeat and error fields in snake_case', function (): void {
    $headers = $this->authHeaders();

    $created = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/job-fields'],
    ], headers: $headers);

    $this->processAllJobs();

    $job = $this->http->get('/api/v1/jobs/' . $created->body['job_ids'][0], headers: $headers);
    $job->assertOk();
    expect($job->body['job'])->toHaveKeys([
        'progress_current',
        'progress_total',
        'progress_percent',
        'progress_label',
        'heartbeat_at',
        'last_error',
    ])->and($job->body['job']['progress_total'])->toBe(3);
});

test('invalid command payloads return stable error envelopes', function (): void {
    $headers = $this->authHeaders();

    $missingType = $this->http->post('/api/v1/commands', ['options' => []], headers: $headers);
    $missingType->assertStatus(Status::BAD_REQUEST);
    expect($missingType->body['error'])->toBe([
        'code' => 'validation_error',
        'message' => 'type is required.',
    ]);

    $invalidPayload = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => [],
    ], headers: $headers);
    $invalidPayload->assertStatus(Status::BAD_REQUEST);
    expect($invalidPayload->body['error']['code'])->toBe('validation_error')
        ->and($invalidPayload->body['error']['message'])->toContain('source_uri');
});

test('authenticated events endpoint returns an event stream response', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->get('/api/v1/events', headers: $headers);
    $response->assertOk();
    expect($response->response)->toBeInstanceOf(EventStream::class);
    $response->assertHasHeader('content-type');
});

test('sse job failed notifications redact secrets in last_error', function (): void {
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

    $job->state = JobState::Failed;
    $job->lastError = 'Bearer stashd_pat_' . str_repeat('a', 40) . ' failed upstream';
    $job->save();

    $publisher->jobFailed($job);

    $notification = EventNotificationRecord::select()
        ->where('eventType = ?', 'job.failed')
        ->orderBy('createdAt', \Tempest\Database\Direction::DESC)
        ->first();

    $payload = json_decode($notification->payloadJson, true, flags: JSON_THROW_ON_ERROR);
    expect($payload['last_error'])->not->toContain('stashd_pat_')
        ->and($payload['last_error'])->toContain('[REDACTED]');
});

test('event notifications are ephemeral and not authoritative over domain records', function (): void {
    $headers = $this->authHeaders();

    $created = $this->http->post('/api/v1/commands', [
        'type' => 'system.storage_check',
        'options' => [],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $this->processAllJobs();

    $job = $this->http->get('/api/v1/jobs/' . $created->body['job_ids'][0], headers: $headers);
    $job->assertOk();

    foreach (EventNotificationRecord::select()->all() as $notification) {
        $notification->delete();
    }

    $jobAfterClear = $this->http->get('/api/v1/jobs/' . $created->body['job_ids'][0], headers: $headers);
    $jobAfterClear->assertOk();
    expect($jobAfterClear->body['job']['state'])->toBe('ready');
});

test('bearer auth takes precedence over session for the same request', function (): void {
    $users = $this->container->get(\App\Auth\UserRepository::class);
    $auth = $this->container->get(AuthService::class);

    $owner = $users->createOwner(
        email: 'owner@stashd.test',
        username: 'owner',
        passwordHash: password_hash('secret-password', PASSWORD_DEFAULT),
    );

    $login = $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertOk();

    useSessionCookieFrom($login);

    $token = $auth->createApiToken($owner, 'bearer-precedence');
    $headers = ['Authorization' => 'Bearer ' . $token['token']];

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertOk();
    $this->http->get('/api/v1/auth/me')->assertOk();
});

test('revoked bearer token is rejected even when session cookie would authenticate', function (): void {
    $auth = $this->container->get(AuthService::class);
    $users = $this->container->get(\App\Auth\UserRepository::class);

    $owner = $users->createOwner(
        email: 'owner@stashd.test',
        username: 'owner',
        passwordHash: password_hash('secret-password', PASSWORD_DEFAULT),
    );

    $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertOk();

    $created = $auth->createApiToken($owner, 'revoke-with-session');
    $headers = ['Authorization' => 'Bearer ' . $created['token']];

    $auth->revokeApiToken($owner, \App\Support\PrefixedUlid::parse($created['id']));

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertStatus(Status::UNAUTHORIZED);
});
