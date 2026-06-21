<?php

declare(strict_types=1);

namespace App\Stashes;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;

#[Table(name: 'stash_items')]
final class StashItemRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $stashId,
        public string $mediaItemId,
        public StashItemState $state,
        public ?string $stashInputId = null,
        public ?int $position = null,
        public ?int $seasonNumber = null,
        public ?int $episodeNumber = null,
        public ?string $seasonTitle = null,
        public ?string $displayTitle = null,
        public ?string $displayDescription = null,
        public ?DateTime $firstSeenAt = null,
        public ?DateTime $lastSeenAt = null,
        public ?DateTime $removedAt = null,
        public ?string $removedReason = null,
        public ?string $ignoredReason = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
