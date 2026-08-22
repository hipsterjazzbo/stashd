<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$sandbox = $base . '/.jellyfin-sandbox';
$staging = $sandbox . '/staging';
$etc = $sandbox . '/etc';
@mkdir($staging, 0770, true);
@mkdir($etc, 0770, true);
file_put_contents($etc . '/passwd', "root:x:0:0:root:/root:/bin/sh\nstashd:x:1000:1000:stashd:/home/stashd:/bin/sh\n");
file_put_contents($etc . '/group', "root:x:0:\nstashd:x:1000:\n");

$bwrap = [
    'bwrap', '--die-with-parent', '--new-session', '--unshare-user', '--unshare-pid',
    '--unshare-ipc', '--unshare-uts', '--unshare-net', '--clearenv',
    '--ro-bind', $base . '/jellyfin-plugin', '/plugin', '--bind', $staging, '/staging',
    '--tmpfs', '/tmp', '--dev', '/dev', '--ro-bind', '/usr', '/usr', '--ro-bind', '/bin', '/bin',
    '--ro-bind', '/lib', '/lib', '--ro-bind', '/lib64', '/lib64', '--ro-bind', '/sbin', '/sbin',
    '--ro-bind', $etc, '/etc', '--dir', '/run', '--dir', '/home', '--dir', '/root',
    '--chdir', '/plugin', '--setenv', 'HOME', '/tmp', '--setenv', 'PATH', '/usr/local/bin:/usr/bin:/bin',
    '--', 'php', '/plugin/plugin.php',
];
$command = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $bwrap));
$pipes = [];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (! is_resource($process)) exit(50);

$mode = getenv('SPIKE_JELLYFIN_MODE') ?: 'lifecycle';
fwrite($pipes[0], json_encode(['event' => 'invoke', 'mode' => $mode], JSON_THROW_ON_ERROR) . "\n");
fflush($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$buffer = '';
$stderr = '';
$requests = [];
$fixtureAuth = 'fixture-jellyfin-token';
$deadline = microtime(true) + 10;

while (microtime(true) < $deadline) {
    $buffer .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    while (($newline = strpos($buffer, "\n")) !== false) {
        $line = trim(substr($buffer, 0, $newline));
        $buffer = substr($buffer, $newline + 1);
        if ($line === '') continue;
        $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (($message['event'] ?? null) === 'result' || ($message['event'] ?? null) === 'error') {
            echo json_encode([
                'plugin' => $message, 'requests' => $requests, 'stderr' => $stderr,
                'staging' => file_get_contents($staging . '/jellyfin-staged.txt') ?: null,
                'runner_uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
                'runner_gid' => function_exists('posix_getegid') ? posix_getegid() : null,
            ], JSON_THROW_ON_ERROR) . "\n";
            fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
            exit($message['event'] === 'result' ? 0 : 1);
        }
        if (($message['event'] ?? null) === 'publication') {
            file_put_contents($staging . '/jellyfin-staged.txt', "materialized authoritative file\n");
            fwrite($pipes[0], json_encode(['event' => 'materialized', 'files' => $message['publication']['files'] ?? []], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            continue;
        }
        if (($message['event'] ?? null) !== 'http.request') continue;

        $url = (string) ($message['url'] ?? '');
        $method = (string) ($message['method'] ?? '');
        $credentialName = (string) ($message['credential'] ?? '');
        $injectedHeader = $credentialName === 'jellyfin-api-token' ? $fixtureAuth : null;
        $requests[] = [
            'method' => $method, 'url' => $url, 'credential_name' => $credentialName,
            'raw_credential_seen' => false, 'credential_injected' => $injectedHeader !== null,
        ];
        $allowed = str_starts_with($url, 'http://fixture.jellyfin/');
        $authenticated = $injectedHeader === $fixtureAuth && $mode !== 'bad-auth';
        if (! $allowed) {
            $response = ['id' => $message['id'] ?? 1, 'error' => 'destination denied'];
        } elseif (! $authenticated) {
            $response = ['id' => $message['id'] ?? 1, 'result' => ['status' => 401, 'body' => '{}']];
        } elseif ($method === 'GET' && str_ends_with($url, '/Library/Refresh')) {
            $response = ['id' => $message['id'] ?? 1, 'result' => ['status' => 405, 'body' => '{}']];
        } elseif ($method === 'POST' && str_ends_with($url, '/Library/Refresh')) {
            $response = $mode === 'refresh-fail'
                ? ['id' => $message['id'] ?? 1, 'result' => ['status' => 500, 'body' => '{}']]
                : ['id' => $message['id'] ?? 1, 'result' => ['status' => 200, 'body' => '{"Items":[]}']];
        } elseif ($method === 'GET' && str_ends_with($url, '/Library/MediaFolders')) {
            $response = ['id' => $message['id'] ?? 1, 'result' => ['status' => 200, 'body' => '{"Items":[{"Id":"abc","Name":"TV"},{"Id":"def","Name":"Movies"}]}']];
        } else {
            $response = ['id' => $message['id'] ?? 1, 'result' => ['status' => 404, 'body' => '{}']];
        }
        fwrite($pipes[0], json_encode($response, JSON_THROW_ON_ERROR) . "\n");
        fflush($pipes[0]);
    }
    $status = proc_get_status($process);
    if (! $status['running']) break;
    usleep(10_000);
}

fwrite(STDERR, json_encode(['error' => 'jellyfin runner timeout', 'stderr' => $stderr]) . "\n");
proc_terminate($process, 9);
exit(51);
