<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Commands\CommandId;
use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use App\System\State\StateTransitionService;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class JobRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    /** @param array<string, mixed>|null $payload */
    public function create(
        JobIntent $intent,
        ?CommandId $commandId = null,
        ?string $entityType = null,
        ?PrefixedUlid $entityId = null,
        int $priority = 100,
        ?array $payload = null,
    ): JobRecord {
        $id = $this->ids->generate('job')->toString();
        $record = new JobRecord(
            commandId: $commandId,
            intent: $intent,
            entityType: $entityType,
            entityId: $entityId?->toString(),
            state: JobState::Pending,
            priority: $priority,
            payload: $payload,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(JobRecord::class)->insert($record)->execute();

        return $record;
    }

    public function find(JobId $id): ?JobRecord
    {
        return JobRecord::findById($id->toPrimaryKey());
    }

    public function save(JobRecord $record): JobRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    public function claimNextPending(StateTransitionService $transitions): ?JobRecord
    {
        $record = JobRecord::select()
            ->where('state = ? AND (scheduledAt IS NULL OR scheduledAt <= ?)', JobState::Pending, DateTime::now(Timezone::UTC))
            ->orderBy('priority', Direction::ASC)
            ->orderBy('createdAt', Direction::ASC)
            ->first();

        if ($record === null) {
            return null;
        }

        $record->attempts++;
        $record->startedAt = DateTime::now(Timezone::UTC);
        $record->heartbeatAt = DateTime::now(Timezone::UTC);
        $record->lastError = null;
        $this->save($record);

        return $transitions->transitionJob($record, JobState::Processing);
    }

    /** @return list<JobRecord> */
    public function listProcessingStale(DateTime $staleBefore): array
    {
        return JobRecord::select()
            ->where('state = ? AND heartbeatAt IS NOT NULL AND heartbeatAt < ?', JobState::Processing, $staleBefore)
            ->all();
    }

    /** @return list<JobRecord> */
    public function listRecent(int $limit = 50): array
    {
        return JobRecord::select()
            ->orderBy('createdAt', Direction::DESC)
            ->limit($limit)
            ->all();
    }

    /** @return list<JobRecord> */
    public function listForCommand(CommandId $commandId): array
    {
        return JobRecord::select()
            ->where('commandId = ?', $commandId->toString())
            ->orderBy('createdAt', Direction::ASC)
            ->all();
    }
}
