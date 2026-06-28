<?php

declare(strict_types=1);

namespace App\Broadcasts;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;

#[Table(name: 'broadcasts')]
final class BroadcastRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $stashId,
        public string $type,
        public string $name,
        public string $slug,
        public BroadcastState $state,
        public ?string $tokenSecretId = null,
        public ?string $tokenPreview = null,
        public ?string $settingsJson = null,
        public ?DateTime $lastPlannedAt = null,
        public ?DateTime $lastBuiltAt = null,
        public ?DateTime $lastVerifiedAt = null,
        public ?string $lastError = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
