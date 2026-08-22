<?php

declare(strict_types=1);

final class RpcHttp
{
    public function request(string $method, string $url, string $credential = 'jellyfin-api-token'): array
    {
        echo json_encode([
            'event' => 'http.request', 'id' => 1, 'method' => $method,
            'url' => $url, 'credential' => $credential, 'headers' => [], 'body' => '',
        ], JSON_THROW_ON_ERROR) . "\n";
        $line = fgets(STDIN);
        if ($line === false) throw new RuntimeException('HTTP broker closed.');
        $response = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (($response['error'] ?? null) !== null) throw new RuntimeException((string) $response['error']);
        return $response['result'];
    }
}

final class JellyfinBroadcast
{
    public function __construct(private readonly RpcHttp $http) {}

    /** @return array{choices: list<array{value: string, label: string}>} */
    public function discoverLibraries(): array
    {
        $response = $this->http->request('GET', 'http://fixture.jellyfin/Library/MediaFolders');
        if ($response['status'] !== 200) throw new RuntimeException('Jellyfin library discovery failed.');
        $json = json_decode((string) $response['body'], true, flags: JSON_THROW_ON_ERROR);
        $choices = [];
        foreach ($json['Items'] ?? [] as $item) {
            if (is_array($item) && isset($item['Id'], $item['Name'])) {
                $choices[] = ['value' => (string) $item['Id'], 'label' => (string) $item['Name']];
            }
        }
        return ['choices' => $choices];
    }

    /** @return array{files: list<array{item_id: string, source_reference: string, relative_path: string}>} */
    public function publish(): array
    {
        $publication = ['files' => [[
            'item_id' => 'item-1', 'source_reference' => 'asset-video-1',
            'relative_path' => 'TV/Season 01/S01E01 - Native PHP Demo.mp4',
        ]]];
        echo json_encode(['event' => 'publication', 'publication' => $publication], JSON_THROW_ON_ERROR) . "\n";
        return $publication;
    }

    /** @param array<string, mixed> $publication */
    public function finalize(array $publication): array
    {
        $materialized = fgets(STDIN);
        if ($materialized === false) throw new RuntimeException('Host did not confirm materialization.');
        $message = json_decode($materialized, true, flags: JSON_THROW_ON_ERROR);
        if (($message['event'] ?? null) !== 'materialized') throw new RuntimeException('Unexpected host lifecycle message.');
        $response = $this->http->request('POST', 'http://fixture.jellyfin/Library/Refresh');
        if ($response['status'] !== 200) throw new RuntimeException('Jellyfin refresh failed after materialization.');
        return ['publication' => $publication, 'refresh' => 'complete'];
    }
}

function sandboxChecks(): array
{
    $read = static fn (string $path): bool => @file_get_contents($path) !== false;
    $mutation = @file_put_contents('/plugin/MUTATION_TEST', 'owned');
    $staging = @file_put_contents('/staging/jellyfin-staged.txt', "native jellyfin output\n");
    $tmp = @file_put_contents('/tmp/jellyfin-private.txt', "private\n");
    $socket = @fsockopen('fixture.jellyfin', 80, $code, $message, 1);
    if (is_resource($socket)) fclose($socket);
    return [
        'vault' => $read('/vault/DO_NOT_READ'), 'app' => $read('/app/secret.txt'),
        'data' => $read('/data/database.env'), 'proc' => $read('/proc/1/environ'),
        'plugin_mutation_bytes' => $mutation, 'staging_bytes' => $staging, 'tmp_bytes' => $tmp,
        'direct_network' => is_resource($socket), 'env_database' => getenv('STASHD_DATABASE_URL') ?: null,
        'env_encryption' => getenv('STASHD_ENCRYPTION_KEY') ?: null,
        'uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
        'gid' => function_exists('posix_getegid') ? posix_getegid() : null,
    ];
}

$invocation = fgets(STDIN);
if ($invocation === false) { fwrite(STDERR, "invocation missing\n"); exit(40); }
$input = json_decode($invocation, true, flags: JSON_THROW_ON_ERROR);
$http = new RpcHttp();
$plugin = new JellyfinBroadcast($http);

try {
    $mode = (string) ($input['mode'] ?? 'lifecycle');
    $checks = sandboxChecks();
    if ($mode === 'bad-auth') {
        $result = $plugin->discoverLibraries();
    } elseif ($mode === 'unauthorized-destination') {
        $result = $http->request('GET', 'http://fixture.forbidden/blocked');
    } else {
        $choices = $plugin->discoverLibraries();
        $publication = $plugin->publish();
        $finalized = $plugin->finalize($publication);
        $result = ['choices' => $choices['choices'], 'publication' => $publication, 'finalized' => $finalized];
    }
    echo json_encode(['event' => 'result', 'result' => $result, 'sandbox' => $checks], JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $exception) {
    echo json_encode(['event' => 'error', 'message' => $exception->getMessage(), 'sandbox' => $checks], JSON_THROW_ON_ERROR) . "\n";
    exit(41);
}
