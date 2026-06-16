<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

final readonly class PodcastEpisode
{
    public function __construct(
        public string $guid,
        public string $title,
        public string $description,
        public string $publishedAt,
        public string $enclosureUrl,
        public int $enclosureLength,
        public string $enclosureMimeType,
    ) {
    }
}
