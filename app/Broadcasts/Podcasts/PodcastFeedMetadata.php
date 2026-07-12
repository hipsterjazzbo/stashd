<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

final readonly class PodcastFeedMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public string $feedUrl,
        public ?string $linkUrl = null,
        public ?string $author = null,
        public ?string $imageUrl = null,
        public ?string $fundingUrl = null,
        public string $language = 'en',
        public bool $explicit = false,
        public bool $complete = false,
        public ?string $podcastGuid = null,
    ) {
    }
}
