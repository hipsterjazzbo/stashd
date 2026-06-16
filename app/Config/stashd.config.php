<?php

declare(strict_types=1);

use App\Config\StashdConfig;

use function Tempest\env;
use function Tempest\root_path;

$resolvePath = static function (string $path): string {
    if (str_starts_with($path, '/') || str_starts_with($path, ':')) {
        return $path;
    }

    return root_path($path);
};

return new StashdConfig(
    dataPath: $resolvePath(env('STASHD_DATA_PATH', env('DATA_PATH', 'data'))),
    mediaPath: $resolvePath(env('STASHD_MEDIA_PATH', env('MEDIA_PATH', 'media'))),
    publicUrl: env('STASHD_PUBLIC_URL', env('BASE_URI', 'http://localhost:8474')),
    logFormat: env('STASHD_LOG_FORMAT', 'text'),
    puid: (int) env('PUID', '1000'),
    pgid: (int) env('PGID', '1000'),
    umask: env('UMASK', '0022'),
    httpPort: env('STASHD_HTTP_PORT', '8474'),
);
