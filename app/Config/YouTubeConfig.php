<?php

declare(strict_types=1);

namespace App\Config;

use function Tempest\Support\str;

final readonly class YouTubeConfig
{
    public function __construct(
        public ?string $dataApiKey = null,
    ) {
    }

    public function hasDataApiKey(): bool
    {
        return $this->dataApiKey !== null && str($this->dataApiKey)->trim()->isNotEmpty();
    }
}
