<?php

declare(strict_types=1);

namespace Tests\Feature;

test('broadcast plugins endpoint lists the discovered plugin keys', function (): void {
    $headers = $this->authHeaders();

    $response = $this->http->get('/api/v1/broadcast-plugins', headers: $headers);
    $response->assertOk();

    $keys = array_column($response->body['plugins'], 'key');

    expect($keys)->toContain('filesystem', 'jellyfin', 'plex', 'podcast');

    $filesystem = array_values(array_filter(
        $response->body['plugins'],
        static fn (array $plugin): bool => $plugin['key'] === 'filesystem',
    ))[0] ?? null;

    expect($filesystem)->not->toBeNull()
        ->and($filesystem['label'])->toBeString()
        ->and($filesystem['supported_file_kinds'])->toBe(['video']);
});
