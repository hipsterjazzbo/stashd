<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Auth\AuthService;
use Tempest\Http\Status;

test('owner setup creates the first user', function (): void {
    $response = $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'username' => 'owner',
        'password' => 'secret-password',
    ]);

    $response->assertStatus(Status::CREATED);
    expect($response->body['user']['email'])->toBe('owner@stashd.test')
        ->and($response->body['setup_required'])->toBeFalse()
        ->and($this->container->get(AuthService::class)->isSetupRequired())->toBeFalse();
});

test('setup is rejected when owner already exists', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'username' => 'owner',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $response = $this->http->post('/api/v1/auth/setup', [
        'email' => 'other@stashd.test',
        'username' => 'other',
        'password' => 'other-password',
    ]);

    $response->assertStatus(Status::CONFLICT);
    expect($response->body['error']['code'])->toBe('setup_already_completed');
});

test('login and session me endpoint work', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'username' => 'owner',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertOk();

    $me = $this->http->get('/api/v1/auth/me');
    $me->assertOk();
    expect($me->body['user']['username'])->toBe('owner');
});

test('api token authenticates protected routes', function (): void {
    $headers = $this->authHeaders();

    $tokenList = $this->http->get('/api/v1/auth/tokens', headers: $headers);
    $tokenList->assertOk();
    expect($tokenList->body['tokens'])->toHaveCount(1);

    $preflight = $this->http->post('/api/v1/stashes/preflight', [
        'sourceUri' => 'fake://channel/auth-demo',
    ], headers: $headers);

    $preflight->assertStatus(Status::CREATED);
});

test('protected routes require setup before owner exists', function (): void {
    $response = $this->http->post('/api/v1/stashes/preflight', [
        'sourceUri' => 'fake://channel/no-auth',
    ]);

    $response->assertStatus(Status::FORBIDDEN);
    expect($response->body['error']['code'])->toBe('setup_required');
});

test('protected routes require authentication after setup', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'username' => 'owner',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $this->http->post('/api/v1/auth/logout')->assertOk();

    $response = $this->http->post('/api/v1/stashes/preflight', [
        'sourceUri' => 'fake://channel/no-token',
    ]);

    $response->assertStatus(Status::UNAUTHORIZED);
    expect($response->body['error']['code'])->toBe('authentication_required');
});

test('login fails before setup with setup_required', function (): void {
    $response = $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ]);

    $response->assertStatus(Status::FORBIDDEN);
    expect($response->body['error']['code'])->toBe('setup_required');
});

test('invalid login credentials are rejected', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'username' => 'owner',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $response = $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(Status::UNAUTHORIZED);
    expect($response->body['error']['code'])->toBe('invalid_credentials');
});

test('api tokens can be revoked', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'username' => 'owner',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $created = $this->http->post('/api/v1/auth/tokens', ['name' => 'revoke-me'])->assertStatus(Status::CREATED);
    $headers = ['Authorization' => 'Bearer ' . $created->body['token']];

    $this->http->post('/api/v1/auth/logout')->assertOk();

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertOk();

    $this->http->delete('/api/v1/auth/tokens/' . $created->body['id'], headers: $headers)->assertOk();

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertStatus(Status::UNAUTHORIZED);
});
