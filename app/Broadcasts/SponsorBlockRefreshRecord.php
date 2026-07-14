<?php

declare(strict_types=1);

namespace App\Broadcasts;

use Tempest\Database\BelongsTo;
use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;

#[Table(name: 'broadcast_sponsorblock_refreshes')]
final class SponsorBlockRefreshRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    #[BelongsTo(ownerJoin: 'broadcastItemId')]
    public BroadcastItemRecord $broadcastItem;

    public function __construct(
        public BroadcastItemId $broadcastItemId,
        public DateTime $nextCheckAt,
        public ?DateTime $lastCheckedAt = null,
        public ?DateTime $completedAt = null,
        public ?string $lastError = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
