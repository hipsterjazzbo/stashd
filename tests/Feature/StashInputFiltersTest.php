<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Stashes\StashItemRecord;
use Tempest\Http\Status;

test('preflight review exposes the universal title-regex filters for every provider', function (): void {
    $headers = $this->authHeaders();

    $preflight = $this->http->post('/api/v1/stashes/preflight', [
        'source_uri' => 'fake://channel/filters-universal',
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $review = $this->http->get(
        '/api/v1/stashes/preflight/' . $preflight->body['command_id'] . '/review',
        headers: $headers,
    )->assertOk();

    $keys = array_column($review->body['preflight']['universal_filters'], 'key');
    expect($keys)->toBe(['title_regex_include', 'title_regex_exclude'])
        ->and($review->body['preflight']['input_options'])->toBe([]);
});

test('preflight review exposes shorts and live toggles for a youtube channel only', function (): void {
    $headers = $this->authHeaders();

    $channel = $this->http->post('/api/v1/stashes/preflight', [
        'source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678',
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $channelReview = $this->http->get(
        '/api/v1/stashes/preflight/' . $channel->body['command_id'] . '/review',
        headers: $headers,
    )->assertOk();

    $optionKeys = array_column($channelReview->body['preflight']['input_options'], 'key');
    expect($optionKeys)->toBe(['include_shorts', 'include_live']);

    $video = $this->http->post('/api/v1/stashes/preflight', [
        'source_uri' => 'https://www.youtube.com/watch?v=demoVideo01',
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $videoReview = $this->http->get(
        '/api/v1/stashes/preflight/' . $video->body['command_id'] . '/review',
        headers: $headers,
    )->assertOk();

    expect($videoReview->body['preflight']['input_options'])->toBe([]);
});

test('add input title-regex include keeps only matching items and marks the rest ignored', function (): void {
    $headers = $this->authHeaders();

    $stash = $this->http->post('/api/v1/stashes', ['name' => 'Regex Include'], headers: $headers)->assertStatus(Status::CREATED);
    $stashId = $stash->body['stash']['id'];

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/regex-include'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $this->http->post('/api/v1/stashes/' . $stashId . '/inputs', [
        'preflight_command_id' => $preflight->body['command_id'],
        'options' => ['title_regex_include' => 'Episode 2'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $items = StashItemRecord::select()->where('stashId = ?', $stashId)->all();
    expect($items)->toHaveCount(3);

    $byState = [];
    foreach ($items as $item) {
        $byState[$item->state->value][] = $item->ignoredReason;
    }

    expect($byState['active'] ?? [])->toHaveCount(1)
        ->and($byState['ignored'] ?? [])->toHaveCount(2)
        ->and(array_unique($byState['ignored']))->toBe(['filter_title_regex']);
});

test('add input title-regex exclude removes matching items and keeps the rest', function (): void {
    $headers = $this->authHeaders();

    $stash = $this->http->post('/api/v1/stashes', ['name' => 'Regex Exclude'], headers: $headers)->assertStatus(Status::CREATED);
    $stashId = $stash->body['stash']['id'];

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/regex-exclude'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $this->http->post('/api/v1/stashes/' . $stashId . '/inputs', [
        'preflight_command_id' => $preflight->body['command_id'],
        'options' => ['title_regex_exclude' => 'Episode 1'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $items = StashItemRecord::select()->where('stashId = ?', $stashId)->all();
    $byState = [];
    foreach ($items as $item) {
        $byState[$item->state->value][] = $item->ignoredReason;
    }

    expect($byState['active'] ?? [])->toHaveCount(2)
        ->and($byState['ignored'] ?? [])->toHaveCount(1)
        ->and($byState['ignored'][0])->toBe('filter_title_regex');
});

test('add input rejects a malformed title-regex pattern', function (): void {
    $headers = $this->authHeaders();

    $stash = $this->http->post('/api/v1/stashes', ['name' => 'Bad Regex'], headers: $headers)->assertStatus(Status::CREATED);

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'fake://channel/bad-regex'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $response = $this->http->post('/api/v1/stashes/' . $stash->body['stash']['id'] . '/inputs', [
        'preflight_command_id' => $preflight->body['command_id'],
        'options' => ['title_regex_include' => '(unterminated'],
    ], headers: $headers);

    $response->assertStatus(Status::BAD_REQUEST);
    expect($response->body['error']['code'])->toBe('validation_error');
});

test('add input excludes shorts and live items from a youtube channel when toggled off', function (): void {
    $headers = $this->authHeaders();

    $this->http->put('/api/v1/providers/youtube/credentials', [
        'api_key' => 'test-api-key',
    ], headers: $headers)->assertOk();

    $stash = $this->http->post('/api/v1/stashes', ['name' => 'No Shorts No Live'], headers: $headers)->assertStatus(Status::CREATED);
    $stashId = $stash->body['stash']['id'];

    $preflight = $this->http->post('/api/v1/commands', [
        'type' => 'stash.preflight',
        'options' => ['source_uri' => 'https://www.youtube.com/channel/UCStashdDemoCh0012345678'],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $this->http->post('/api/v1/stashes/' . $stashId . '/inputs', [
        'preflight_command_id' => $preflight->body['command_id'],
        'options' => ['provider' => ['include_shorts' => false, 'include_live' => false]],
    ], headers: $headers)->assertStatus(Status::CREATED);
    $this->processAllJobs();

    $items = \App\Stashes\StashItemRecord::select()->where('stashId = ?', $stashId)->all();
    expect($items)->toHaveCount(18);

    $reasonsByMediaItemId = [];
    foreach ($items as $item) {
        $media = \App\Vault\MediaItemRecord::findById(new \Tempest\Database\PrimaryKey((string) $item->mediaItemId));
        $reasonsByMediaItemId[$media->providerItemId] = [$item->state->value, $item->ignoredReason];
    }

    expect($reasonsByMediaItemId['StashdVid01'])->toBe(['active', null])
        ->and($reasonsByMediaItemId['StashdVid15'])->toBe(['ignored', 'filter_video_type'])
        ->and($reasonsByMediaItemId['StashdVid16'])->toBe(['ignored', 'filter_video_type'])
        ->and($reasonsByMediaItemId['StashdVid17'])->toBe(['ignored', 'filter_video_type'])
        ->and($reasonsByMediaItemId['StashdVid18'])->toBe(['ignored', 'filter_video_type']);
});
