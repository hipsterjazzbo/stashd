<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$sandbox = $base . '/.sandbox';
$staging = $sandbox . '/staging';
$etc = $sandbox . '/etc';

@mkdir($staging, 0770, true);
@mkdir($etc, 0770, true);
file_put_contents($etc . '/passwd', "root:x:0:0:root:/root:/bin/sh\nstashd:x:1000:1000:stashd:/home/stashd:/bin/sh\n");
file_put_contents($etc . '/group', "root:x:0:\nstashd:x:1000:\n");

$bwrap = [
    'bwrap',
    '--die-with-parent',
    '--new-session',
    '--unshare-user',
    '--unshare-pid',
    '--unshare-ipc',
    '--unshare-uts',
    '--unshare-net',
    '--clearenv',
    '--ro-bind', $base . '/plugin', '/plugin',
    '--bind', $staging, '/staging',
    '--tmpfs', '/tmp',
    '--dev', '/dev',
    '--ro-bind', '/usr', '/usr',
    '--ro-bind', '/bin', '/bin',
    '--ro-bind', '/lib', '/lib',
    '--ro-bind', '/lib64', '/lib64',
    '--ro-bind', '/sbin', '/sbin',
    '--ro-bind', $etc, '/etc',
    '--dir', '/run',
    '--dir', '/home',
    '--dir', '/root',
    '--chdir', '/plugin',
    '--setenv', 'HOME', '/tmp',
    '--setenv', 'PATH', '/usr/local/bin:/usr/bin:/bin',
    '--',
    'php', '/plugin/plugin.php',
];

$command = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $bwrap));
$pipes = [];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (! is_resource($process)) {
    fwrite(STDERR, "could not start bubblewrap\n");
    exit(30);
}

stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$protocol = '';
$stderr = '';
$deadline = microtime(true) + 10;
$sent = [];

while (microtime(true) < $deadline) {
    $protocol .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    while (($newline = strpos($protocol, "\n")) !== false) {
        $line = trim(substr($protocol, 0, $newline));
        $protocol = substr($protocol, $newline + 1);
        if ($line === '') {
            continue;
        }
        $message = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (($message['event'] ?? null) === 'http.get') {
            $url = (string) ($message['params']['url'] ?? '');
            $allowed = str_starts_with($url, 'http://fixture.allowed/');
            fwrite($pipes[0], json_encode([
                'id' => $message['id'],
                'result' => $allowed
                    ? ['status' => 200, 'body' => 'fixture response']
                    : ['status' => 403, 'error' => 'destination denied'],
            ], JSON_THROW_ON_ERROR) . "\n");
            fflush($pipes[0]);
            $sent[] = $url;
        } elseif (($message['event'] ?? null) === 'result') {
            echo json_encode([
                'result' => $message,
                'broker_requests' => $sent,
                'runner_uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
                'runner_gid' => function_exists('posix_getegid') ? posix_getegid() : null,
                'staging_content' => is_file($staging . '/allowed.txt')
                    ? file_get_contents($staging . '/allowed.txt')
                    : null,
                'stderr' => $stderr,
            ], JSON_THROW_ON_ERROR) . "\n";
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            exit($exit === 0 ? 0 : 31);
        }
    }

    $status = proc_get_status($process);
    if (! $status['running']) {
        $stderr .= stream_get_contents($pipes[2]);
        fwrite(STDERR, json_encode(['exit' => $status['exitcode'], 'stderr' => $stderr]) . "\n");
        exit(32);
    }
    usleep(10_000);
}

fwrite(STDERR, json_encode(['error' => 'sandbox timeout', 'stderr' => $stderr]) . "\n");
proc_terminate($process, 9);
exit(33);
