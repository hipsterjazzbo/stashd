<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'broadcast_items')]
final class BroadcastItemRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $broadcastId,
        public string $stashItemId,
        public string $mediaItemId,
        public BroadcastItemState $state,
        public ?string $publishedPath = null,
        public ?string $publishedUri = null,
        public ?string $lastPublishedAt = null,
        public ?string $lastVerifiedAt = null,
        public ?string $lastError = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
