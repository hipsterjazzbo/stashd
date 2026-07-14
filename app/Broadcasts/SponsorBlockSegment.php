<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Timeline\TimelineEntryCategory;

final readonly class SponsorBlockSegment
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $externalId,
        public TimelineEntryCategory $category,
        public float $startSeconds,
        public float $endSeconds,
        public ?string $title,
        public array $raw,
    ) {
    }
}
