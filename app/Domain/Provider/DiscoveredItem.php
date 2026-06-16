<?php

declare(strict_types=1);

namespace App\Domain\Provider;

use Tempest\DateTime\DateTime;

final readonly class DiscoveredItem
{
    /**
     * @param array<string, mixed>|null $rawMetadata
     */
    public function __construct(
        public string $providerItemId,
        public StashdUri $canonicalUri,
        public string $title,
        public ?int $durationSeconds = null,
        public ?DateTime $publishedAt = null,
        public ?StashdUri $thumbnailUri = null,
        public ?array $rawMetadata = null,
    ) {
    }
}
