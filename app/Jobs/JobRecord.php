<?php

declare(strict_types=1);

namespace App\Jobs;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'jobs')]
final class JobRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public ?string $commandId,
        public JobIntent $intent,
        public ?string $entityType,
        public ?string $entityId,
        public JobState $state,
        public int $priority = 100,
        public int $attempts = 0,
        public int $maxAttempts = 3,
        public ?string $scheduledAt = null,
        public ?string $startedAt = null,
        public ?string $finishedAt = null,
        public ?string $heartbeatAt = null,
        public ?int $progressCurrent = null,
        public ?int $progressTotal = null,
        public ?float $progressPercent = null,
        public ?string $progressLabel = null,
        public ?float $progressRate = null,
        public ?int $progressEtaSeconds = null,
        public ?string $lastError = null,
        public ?string $payloadJson = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
