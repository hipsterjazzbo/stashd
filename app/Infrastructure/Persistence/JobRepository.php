<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Job\JobIntent;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Domain\Support\PrefixedUlid;
use App\Domain\Support\PrefixedUlidGenerator;
use App\Services\State\StateTransitionService;
use InvalidArgumentException;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class JobRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        JobIntent $intent,
        ?PrefixedUlid $commandId = null,
        ?string $entityType = null,
        ?PrefixedUlid $entityId = null,
        int $priority = 100,
        ?array $payload = null,
    ): JobRecord {
        $id = $this->ids->generate('job')->toString();
        $record = new JobRecord(
            commandId: $commandId?->toString(),
            intent: $intent,
            entityType: $entityType,
            entityId: $entityId?->toString(),
            state: JobState::Pending,
            priority: $priority,
            payloadJson: $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(JobRecord::class)->insert($record)->execute();

        return JobRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist job record.');
    }

    public function find(PrefixedUlid $id): ?JobRecord
    {
        return JobRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(JobRecord $record): JobRecord
    {
        $record->updatedAt = RecordTimestamps::now();
        $record->save();

        return $record;
    }

    public function claimNextPending(StateTransitionService $transitions): ?JobRecord
    {
        $record = JobRecord::select()
            ->where('state = ?', JobState::Pending)
            ->orderBy('priority', Direction::ASC)
            ->orderBy('createdAt', Direction::ASC)
            ->first();

        if ($record === null) {
            return null;
        }

        $record->attempts++;
        $record->startedAt = RecordTimestamps::now();
        $record->heartbeatAt = RecordTimestamps::now();
        $record->lastError = null;
        $this->save($record);

        return $transitions->transitionJob($record, JobState::Processing);
    }

    /** @return list<JobRecord> */
    public function listProcessingStale(string $staleBefore): array
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
    public function listForCommand(string $commandId): array
    {
        return JobRecord::select()
            ->where('commandId = ?', $commandId)
            ->orderBy('createdAt', Direction::ASC)
            ->all();
    }
}
