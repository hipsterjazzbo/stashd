<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Stashes\StashId;
use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;
use Tempest\Mapper\Hidden;

#[Table(name: 'broadcasts')]
final class BroadcastRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    /** @param array<string, mixed>|null $settings */
    public function __construct(
        public StashId $stashId,
        public string $type,
        public string $name,
        public string $slug,
        public BroadcastState $state,
        #[Hidden]
        public ?string $tokenSecretId = null,
        public ?string $tokenPreview = null,
        public ?array $settings = null,
        public ?DateTime $lastPlannedAt = null,
        public ?DateTime $lastBuiltAt = null,
        public ?DateTime $lastVerifiedAt = null,
        public ?string $lastError = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
