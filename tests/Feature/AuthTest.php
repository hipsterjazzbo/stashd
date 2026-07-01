<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\ApiTokenRecord;
use App\Auth\ApiTokenScopes;
use App\Auth\AuthService;
use Tempest\Database\Database;
use Tempest\Database\Query;
use Tempest\Http\Status;

test('owner setup creates the first user', function (): void {
    $response = $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
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
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $response = $this->http->post('/api/v1/auth/setup', [
        'email' => 'other@stashd.test',
        'password' => 'other-password',
    ]);

    $response->assertStatus(Status::CONFLICT);
    expect($response->body['error']['code'])->toBe('setup_already_completed');
});

test('login and session me endpoint work', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $login = $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertOk();

    useSessionCookieFrom($login);

    $me = $this->http->get('/api/v1/auth/me');
    $me->assertOk();
    expect($me->body['user']['email'])->toBe('owner@stashd.test');
});

test('login sets an httponly session cookie carrying a revocable token', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $response = $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertOk();

    $response->assertHasCookie(AuthService::SESSION_COOKIE, function (string $value): void {
        expect($value)->not->toBeEmpty();
    });
});

test('the session cookie issued at login authenticates protected routes', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $login = $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertOk();

    $this->http->get('/api/v1/auth/me')->assertStatus(Status::UNAUTHORIZED);

    useSessionCookieFrom($login);

    $me = $this->http->get('/api/v1/auth/me');
    $me->assertOk();
    expect($me->body['user']['email'])->toBe('owner@stashd.test');
});

test('logout revokes the session token and clears the cookie', function (): void {
    $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    $login = $this->http->post('/api/v1/auth/login', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertOk();

    useSessionCookieFrom($login);

    $loggedInCookie = $_COOKIE[AuthService::SESSION_COOKIE];

    $logout = $this->http->post('/api/v1/auth/logout');
    $logout->assertOk();
    $logout->assertHasCookie(AuthService::SESSION_COOKIE, '');

    // The revoked cookie must not work even if a client kept holding onto it.
    $_COOKIE[AuthService::SESSION_COOKIE] = $loggedInCookie;
    $this->http->get('/api/v1/auth/me')->assertStatus(Status::UNAUTHORIZED);
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
    $setup = $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    useSessionCookieFrom($setup);

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
    $setup = $this->http->post('/api/v1/auth/setup', [
        'email' => 'owner@stashd.test',
        'password' => 'secret-password',
    ])->assertStatus(Status::CREATED);

    useSessionCookieFrom($setup);

    $created = $this->http->post('/api/v1/auth/tokens', ['name' => 'revoke-me'])->assertStatus(Status::CREATED);
    $headers = ['Authorization' => 'Bearer ' . $created->body['token']];

    $this->http->post('/api/v1/auth/logout')->assertOk();

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertOk();

    $this->http->delete('/api/v1/auth/tokens/' . $created->body['id'], headers: $headers)->assertOk();

    $this->http->get('/api/v1/auth/me', headers: $headers)->assertStatus(Status::UNAUTHORIZED);
});

test('api token scopes are stored as a typed value object', function (): void {
    $headers = $this->authHeaders();

    $created = $this->http->post('/api/v1/auth/tokens', [
        'name' => 'scoped-token',
        'scopes' => ['media:read', ' media:read ', '', 'broadcast:write', 123],
    ], headers: $headers)->assertStatus(Status::CREATED);

    $record = ApiTokenRecord::select()
        ->where('id = ?', $created->body['id'])
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->scopesJson)->toBeInstanceOf(ApiTokenScopes::class)
        ->and($record->scopesJson?->toArray())->toBe(['media:read', 'broadcast:write']);

    $row = $this->container->get(Database::class)->fetchFirst(new Query(
        'SELECT scopesJson FROM api_tokens WHERE id = ?',
        bindings: [$created->body['id']],
    ));
    $storedScopes = json_decode((string) $row['scopesJson'], true, flags: JSON_THROW_ON_ERROR);

    expect($storedScopes)->toBe([
        'type' => 'api_token_scopes',
        'data' => [
            'values' => ['media:read', 'broadcast:write'],
        ],
    ]);

    $tokenList = $this->http->get('/api/v1/auth/tokens', headers: $headers);

    $scopedToken = array_values(array_filter(
        $tokenList->body['tokens'],
        static fn (array $token): bool => $token['id'] === $created->body['id'],
    ))[0] ?? null;

    expect($scopedToken)->not->toBeNull()
        ->and($scopedToken['scopes'])->toBe(['media:read', 'broadcast:write']);
});
