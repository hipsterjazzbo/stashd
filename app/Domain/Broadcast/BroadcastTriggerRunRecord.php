<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'broadcast_trigger_runs')]
final class BroadcastTriggerRunRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $triggerId,
        public BroadcastTriggerRunState $state,
        public ?string $reason = null,
        public ?string $startedAt = null,
        public ?string $finishedAt = null,
        public ?string $responseSummary = null,
        public ?string $error = null,
        public ?string $createdAt = null,
    ) {
    }
}
