<?php

declare(strict_types=1);

use Tempest\Database\Config\SQLiteConfig;

use function Tempest\env;

$path = env('DB_DATABASE', ':memory:');

return new SQLiteConfig(path: $path);
