<?php

declare(strict_types=1);

namespace App\Stashes;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'stash_inputs')]
final class StashInputRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $stashId,
        public string $providerKey,
        public StashInputType $inputType,
        public string $sourceUri,
        public string $providerInputId,
        public StashInputState $state,
        public int $consecutiveFailures = 0,
        public ?string $title = null,
        public ?SyncMode $syncMode = null,
        public ?string $lastCheckedAt = null,
        public ?string $nextCheckAt = null,
        public ?string $lastSuccessAt = null,
        public ?string $lastFailureAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
