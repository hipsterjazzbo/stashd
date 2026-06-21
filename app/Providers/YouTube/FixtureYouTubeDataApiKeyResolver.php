<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

final readonly class FixtureYouTubeDataApiKeyResolver implements YouTubeDataApiKeyResolver
{
    public function __construct(
        private ?string $apiKey = null,
    ) {
    }

    public function key(): ?string
    {
        return $this->apiKey;
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== null;
    }
}
