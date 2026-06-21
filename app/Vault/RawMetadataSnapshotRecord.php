<?php

declare(strict_types=1);

namespace App\Vault;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;

#[Table(name: 'raw_metadata_snapshots')]
final class RawMetadataSnapshotRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $mediaItemId,
        public string $providerKey,
        public MetadataSnapshotType $snapshotType,
        public string $rawJson,
        public ?string $stashInputId = null,
        public ?DateTime $createdAt = null,
    ) {
    }
}
