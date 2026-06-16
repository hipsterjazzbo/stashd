<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'broadcasts')]
final class BroadcastRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $stashId,
        public BroadcastType $type,
        public string $name,
        public string $slug,
        public BroadcastState $state,
        public ?string $tokenSecretId = null,
        public ?string $tokenPreview = null,
        public ?string $settingsJson = null,
        public ?string $lastPlannedAt = null,
        public ?string $lastBuiltAt = null,
        public ?string $lastVerifiedAt = null,
        public ?string $lastError = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
