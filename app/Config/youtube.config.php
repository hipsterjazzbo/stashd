<?php

declare(strict_types=1);

use App\Config\YouTubeConfig;

use function Tempest\env;
use function Tempest\Support\str;

return new YouTubeConfig(
    dataApiKey: ($key = env('YOUTUBE_DATA_API_KEY')) !== null && str((string) $key)->trim()->isNotEmpty()
        ? str((string) $key)->trim()->toString()
        : null,
);
