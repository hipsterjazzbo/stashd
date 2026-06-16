<?php

declare(strict_types=1);

namespace App\Config;

use function Tempest\env;

final readonly class YtdlpConfig
{
    public function __construct(
        public string $binary,
        public int $timeoutSeconds,
        private bool $realDownloadsEnabledDefault,
        public string $videoFormatSelector,
        public string $audioFormat,
        public int $audioQualityKbps,
    ) {
    }

    public function realDownloadsEnabled(): bool
    {
        $override = env('STASHD_REAL_DOWNLOADS_ENABLED');

        if ($override !== null && $override !== '') {
            return filter_var($override, FILTER_VALIDATE_BOOL);
        }

        return $this->realDownloadsEnabledDefault;
    }
}
