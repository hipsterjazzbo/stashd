<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

final readonly class ResolvedYouTubeChannel
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $avatarUri = null,
        public ?int $estimatedVideoCount = null,
    ) {
    }
}
