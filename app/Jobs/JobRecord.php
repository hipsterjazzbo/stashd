<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\DurationSecondsCaster;
use App\Support\DurationSecondsSerializer;
use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Duration;
use Tempest\Mapper\CastWith;
use Tempest\Mapper\SerializeWith;

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
        public ?DateTime $scheduledAt = null,
        public ?DateTime $startedAt = null,
        public ?DateTime $finishedAt = null,
        public ?DateTime $heartbeatAt = null,
        public ?int $progressCurrent = null,
        public ?int $progressTotal = null,
        public ?float $progressPercent = null,
        public ?string $progressLabel = null,
        public ?float $progressRate = null,
        #[CastWith(DurationSecondsCaster::class)]
        #[SerializeWith(DurationSecondsSerializer::class)]
        public ?Duration $progressEtaSeconds = null,
        public ?string $lastError = null,
        public ?string $payloadJson = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
