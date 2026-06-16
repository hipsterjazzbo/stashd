<?php

declare(strict_types=1);

namespace App\Broadcasts;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'broadcast_triggers')]
final class BroadcastTriggerRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $broadcastId,
        public BroadcastTriggerType $type,
        public bool $enabled,
        public BroadcastTriggerState $state,
        public ?string $settingsJson = null,
        public ?string $lastTriggeredAt = null,
        public ?string $lastSuccessAt = null,
        public ?string $lastFailureAt = null,
        public ?string $lastError = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
