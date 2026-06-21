<?php

declare(strict_types=1);

namespace App\Vault;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'media_items')]
final class MediaItemRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $providerKey,
        public string $providerItemId,
        public string $canonicalUri,
        public string $title,
        public MediaItemState $state,
        public UpstreamState $upstreamState,
        public ?string $description = null,
        public ?string $creatorName = null,
        public ?string $creatorProviderId = null,
        public ?int $durationSeconds = null,
        public ?string $publishedAt = null,
        public ?string $thumbnailUri = null,
        public ?string $metadataCapturedAt = null,
        public ?string $metadataRefreshedAt = null,
        public ?string $lastSeenUpstreamAt = null,
        public ?string $contentType = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
