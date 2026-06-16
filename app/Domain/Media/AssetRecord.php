<?php

declare(strict_types=1);

namespace App\Domain\Media;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'assets')]
final class AssetRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public AssetRole $role,
        public AssetKind $kind,
        public AssetState $state,
        public ?string $mediaItemId = null,
        public ?string $broadcastId = null,
        public ?string $broadcastItemId = null,
        public ?string $path = null,
        public ?string $relativePath = null,
        public ?string $mimeType = null,
        public ?string $container = null,
        public ?string $videoCodec = null,
        public ?string $audioCodec = null,
        public ?string $language = null,
        public ?int $sizeBytes = null,
        public ?string $checksum = null,
        public ?int $durationSeconds = null,
        public ?string $derivedFromAssetId = null,
        public ?string $lastVerifiedAt = null,
        public ?string $missingAt = null,
        public ?string $missingReason = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
