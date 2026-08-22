<?php

declare(strict_types=1);

$attempts = [];
$read = static function (string $path) use (&$attempts): ?string {
    $value = @file_get_contents($path);
    $attempts[$path] = $value === false ? 'denied' : 'read';
    return $value === false ? null : $value;
};

$read('/vault/DO_NOT_READ');
$read('/proc/1/environ');
$read('/app/secret.txt');
$read('/data/database.env');
$read('/run/secrets/token');
$read('/home/outer-user-secret');

$pluginMutation = @file_put_contents('/plugin/MUTATION_TEST', 'owned');
$staging = @file_put_contents('/staging/allowed.txt', "staged output\n");
$tmp = @file_put_contents('/tmp/allowed.txt', "private temp\n");

$directNetwork = @fsockopen('fixture.allowed', 80, $errorCode, $errorMessage, 1);
if (is_resource($directNetwork)) {
    fclose($directNetwork);
    $directNetworkResult = 'connected';
} else {
    $directNetworkResult = 'denied';
}

echo json_encode([
    'event' => 'http.get',
    'id' => 1,
    'method' => 'http.get',
    'params' => ['url' => 'http://fixture.allowed/allowed'],
], JSON_THROW_ON_ERROR) . "\n";

$response = fgets(STDIN);
if ($response === false) {
    fwrite(STDERR, "broker closed before response\n");
    exit(20);
}

$broker = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

echo json_encode([
    'event' => 'http.get',
    'id' => 2,
    'method' => 'http.get',
    'params' => ['url' => 'http://fixture.forbidden/denied'],
], JSON_THROW_ON_ERROR) . "\n";

$deniedResponse = fgets(STDIN);
if ($deniedResponse === false) {
    fwrite(STDERR, "broker closed before denied response\n");
    exit(21);
}

echo json_encode([
    'event' => 'result',
    'php' => PHP_VERSION,
    'uid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
    'gid' => function_exists('posix_getegid') ? posix_getegid() : null,
    'uname' => php_uname(),
    'attempts' => $attempts,
    'plugin_mutation_bytes' => $pluginMutation,
    'broker' => $broker,
    'denied_broker' => json_decode($deniedResponse, true, flags: JSON_THROW_ON_ERROR),
    'staging_bytes' => $staging,
    'tmp_bytes' => $tmp,
    'direct_network' => $directNetworkResult,
    'env' => [
        'database' => getenv('STASHD_DATABASE_URL') ?: null,
        'encryption' => getenv('STASHD_ENCRYPTION_KEY') ?: null,
        'all' => array_keys($_ENV),
    ],
], JSON_THROW_ON_ERROR) . "\n";
