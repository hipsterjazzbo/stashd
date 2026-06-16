<?php

declare(strict_types=1);

use Tempest\Database\Config\SQLiteConfig;

use function Tempest\env;
use function Tempest\root_path;

$resolvePath = static function (string $path): string {
    if (str_starts_with($path, '/') || str_starts_with($path, ':')) {
        return $path;
    }

    return root_path($path);
};

$dataPath = $resolvePath(env('STASHD_DATA_PATH', env('DATA_PATH', 'data')));
$databasePath = env('DB_DATABASE') ?? rtrim($dataPath, '/') . '/stashd.sqlite';

if (! str_starts_with($databasePath, '/') && ! str_starts_with($databasePath, ':')) {
    $databasePath = rtrim($dataPath, '/') . '/' . ltrim($databasePath, '/');
}

return new SQLiteConfig(path: $databasePath);
