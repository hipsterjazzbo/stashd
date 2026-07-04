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

test('events stream rejects a new connection once at capacity with a retry-after message', function (): void {
    $connections = $this->container->get(\App\System\Event\SseConnectionRepository::class);

    for ($i = 0; $i < 4; $i++) {
        expect($connections->tryAcquireSlot(4, 15))->not->toBeNull();
    }

    $controller = $this->container->get(\App\System\Event\EventsController::class);
    $request = new \Tempest\Http\GenericRequest(\Tempest\Http\Method::GET, '/api/v1/events');
    $generator = $controller->stream($request)->body;

    // The rejection path yields exactly one message and returns immediately —
    // safe to iterate fully in a test, unlike the accepted path's ~10s poll loop.
    expect($generator)->toBeInstanceOf(\Generator::class);

    $message = $generator->current();
    expect($message)->toBeInstanceOf(\Tempest\Http\ServerSentMessage::class)
        ->and($message->retryAfter)->not->toBeNull();

    $generator->next();
    expect($generator->valid())->toBeFalse();
});

test('sse stream starts fresh connections from now, not from old backlog', function (): void {
    $publisher = $this->container->get(EventPublisher::class);
    $controller = $this->container->get(\App\System\Event\EventsController::class);

    $headers = $this->authHeaders();
    $created = $this->http->post('/api/v1/commands', [
        'type' => 'system.storage_check',
        'options' => [],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $job = JobRecord::findById(new \Tempest\Database\PrimaryKey($created->body['job_ids'][0]));

    // An "old" notification published before any connection opens.
    $publisher->jobProgress($job);

    // stream() snapshots "now" synchronously at call time, before the
    // generator is ever iterated.
    $request = new \Tempest\Http\GenericRequest(\Tempest\Http\Method::GET, '/api/v1/events');
    $generator = $controller->stream($request)->body;

    // A "new" notification published after that snapshot.
    $publisher->jobFailed($job);

    // The first (and only, for this test) message must be the new one — the
    // old backlog entry must not be replayed.
    $message = $generator->current();
    expect($message->event)->toBe('job.failed');

    // The generator is deliberately abandoned mid-iteration (its outer loop
    // sleeps ~1s per iteration, not worth draining in a test) — its
    // try/finally holds a reference cycle back through the controller, so
    // without forcing collection here, the pending release() can fire
    // during a *later*, unrelated test once PHP's cyclic GC gets around to
    // it, by which point that test's container scope owns Database instead.
    unset($generator);
    gc_collect_cycles();
});

test('sse stream resumes from Last-Event-ID instead of replaying already-seen notifications', function (): void {
    $publisher = $this->container->get(EventPublisher::class);
    $controller = $this->container->get(\App\System\Event\EventsController::class);

    $headers = $this->authHeaders();
    $created = $this->http->post('/api/v1/commands', [
        'type' => 'system.storage_check',
        'options' => [],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $job = JobRecord::findById(new \Tempest\Database\PrimaryKey($created->body['job_ids'][0]));

    $publisher->jobProgress($job);
    $lastEventId = $this->container->get(\App\System\Event\EventNotificationRepository::class)->latestSequence();

    $publisher->jobFailed($job);

    $resumedRequest = new \Tempest\Http\GenericRequest(
        \Tempest\Http\Method::GET,
        '/api/v1/events',
        headers: ['Last-Event-ID' => (string) $lastEventId],
    );
    $generator = $controller->stream($resumedRequest)->body;

    // Only the notification published after $lastEventId should be
    // delivered — not a replay of the job.progress one already seen. Stop
    // at the first message: the accepted path's outer loop sleeps ~1s
    // between iterations (unlike the rejection path above), so draining it
    // further isn't worth the test time.
    $message = $generator->current();
    expect($message->event)->toBe('job.failed')
        ->and($message->id)->toBeGreaterThan($lastEventId);

    // See the comment in the previous test — force deterministic cleanup of
    // the abandoned generator's reference cycle before this test ends.
    unset($generator);
    gc_collect_cycles();
});

test('sse notification pruning deletes only rows past the retention window', function (): void {
    $repository = $this->container->get(\App\System\Event\EventNotificationRepository::class);

    $old = $repository->publish('job.progress', ['job_id' => 'old']);
    $recent = $repository->publish('job.progress', ['job_id' => 'recent']);

    new \Tempest\Database\Query(
        "UPDATE event_notifications SET createdAt = datetime('now', '-2 hours') WHERE id = ?",
        [(string) $old->id],
    )->execute();

    $pruned = $repository->pruneOlderThan(1);

    expect($pruned)->toBe(1);

    $remainingIds = array_map(
        static fn (EventNotificationRecord $record): string => (string) $record->id,
        EventNotificationRecord::select()->all(),
    );
    expect($remainingIds)->not->toContain((string) $old->id)
        ->and($remainingIds)->toContain((string) $recent->id);
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

    $payload = $notification->payload;
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

    $owner = $users->createAdmin(
        username: 'owner',
        passwordHash: password_hash('secret-password', PASSWORD_DEFAULT),
    );

    $login = $this->http->post('/api/v1/auth/login', [
        'username' => 'owner',
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

    $owner = $users->createAdmin(
        username: 'owner',
        passwordHash: password_hash('secret-password', PASSWORD_DEFAULT),
    );

    $this->http->post('/api/v1/auth/login', [
        'username' => 'owner',
        'password' => 'secret-password',
    ])->assertOk();

    $created = $auth->createApiToken($owner, 'revoke-with-session');
    $headers = ['Authorization' => 'Bearer ' . $created['token']];

    $auth->revokeApiToken($owner, \App\Auth\ApiTokenId::parse($created['id']));

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertStatus(Status::UNAUTHORIZED);
});
