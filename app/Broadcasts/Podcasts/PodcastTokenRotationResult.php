<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

final readonly class PodcastTokenRotationResult
{
    public function __construct(
        public bool $rotated,
        public string $tokenPreview,
        public bool $revokedOldSecret,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rotated' => $this->rotated,
            'token_preview' => $this->tokenPreview,
            'revoked_old_secret' => $this->revokedOldSecret,
        ];
    }
}
