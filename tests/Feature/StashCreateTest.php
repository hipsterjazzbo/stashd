<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Stashes\StashRecord;
use Tempest\Database\PrimaryKey;
use Tempest\Http\Status;

test('POST stash creates an empty stash with a placeholder name when title is omitted', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/stashes', [], headers: $headers);

    $response->assertStatus(Status::CREATED);
    expect($response->body['stash']['name'])->toBe('New Stash')
        ->and($response->body['stash']['sync_mode'])->toBe('automatic')
        ->and($response->body['stash']['download_policy'])->toBe('video')
        ->and($response->body['stash']['organization_mode'])->toBe('flat');

    $stash = StashRecord::findById(new PrimaryKey($response->body['stash']['id']));
    expect($stash)->not->toBeNull()
        ->and($stash->name)->toBe('New Stash');
});

test('POST stash uses the provided title as the name', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/stashes', [
        'name' => 'My Favourite Channel!',
    ], headers: $headers);

    $response->assertStatus(Status::CREATED);
    expect($response->body['stash']['name'])->toBe('My Favourite Channel!');
});

test('POST stash allows duplicate names because identity comes from the stash id', function (): void {
    $headers = $this->authHeaders();

    $first = $this->http->post('/api/v1/stashes', ['name' => 'Duplicate'], headers: $headers);
    $first->assertStatus(Status::CREATED);

    $second = $this->http->post('/api/v1/stashes', ['name' => 'Duplicate'], headers: $headers);
    $second->assertStatus(Status::CREATED);

    expect($first->body['stash']['id'])->not->toBe($second->body['stash']['id'])
        ->and($first->body['stash']['name'])->toBe('Duplicate')
        ->and($second->body['stash']['name'])->toBe('Duplicate');
});

test('POST stash rejects an unsupported sync_mode', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->post('/api/v1/stashes', [
        'sync_mode' => 'not-a-real-mode',
    ], headers: $headers);

    $response->assertStatus(Status::BAD_REQUEST);
    expect($response->body['error']['code'])->toBe('validation_error');
});

test('POST stash requires authentication', function (): void {
    $this->authHeaders();

    $response = $this->http->post('/api/v1/stashes', ['name' => 'No Auth']);

    $response->assertStatus(Status::UNAUTHORIZED);
});
