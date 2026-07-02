<?php

declare(strict_types=1);

namespace App\Broadcasts;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;
use Tempest\Mapper\Hidden;

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
        #[Hidden]
        public ?string $tokenSecretId = null,
        public ?string $tokenPreview = null,
        public ?string $publishedPath = null,
        public ?string $publishedUri = null,
        public ?DateTime $lastPublishedAt = null,
        public ?DateTime $lastVerifiedAt = null,
        public ?string $lastError = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
