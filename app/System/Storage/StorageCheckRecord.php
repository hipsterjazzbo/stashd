<?php

declare(strict_types=1);

namespace App\System\Storage;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'storage_checks')]
final class StorageCheckRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $storageLocationId,
        public StorageCheckType $checkType,
        public StorageCheckState $state,
        public ?string $message = null,
        public ?string $detailsJson = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
