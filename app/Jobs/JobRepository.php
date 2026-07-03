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

    /**
     * Processing jobs are always included, on top of the $limit most recent.
     * They're claimed oldest-first (see claimNextPending), so a plain
     * "ORDER BY createdAt DESC LIMIT $limit" reliably drops the one actively
     * processing job during a large batch (e.g. backfilling a channel with
     * hundreds of items) -- which hid live download progress from the stash
     * detail page.
     *
     * @return list<JobRecord>
     */
    public function listRecent(int $limit = 50): array
    {
        $processing = JobRecord::select()
            ->where('state = ?', JobState::Processing)
            ->orderBy('createdAt', Direction::ASC)
            ->all();

        $recent = JobRecord::select()
            ->orderBy('createdAt', Direction::DESC)
            ->limit($limit)
            ->all();

        $seen = [];
        $jobs = [];
        foreach ($processing as $job) {
            $seen[(string) $job->id] = true;
            $jobs[] = $job;
        }
        foreach ($recent as $job) {
            $id = (string) $job->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $jobs[] = $job;
        }

        return $jobs;
    }

    /** @return list<JobRecord> */
    public function listForCommand(CommandId $commandId): array
    {
        return JobRecord::select()
            ->where('commandId = ?', $commandId->toString())
            ->orderBy('createdAt', Direction::ASC)
            ->all();
    }

    /**
     * The error message from each media item's most recent download job, but
     * only for items whose most recent attempt is the one that failed --
     * covers what the "why did this fail" tooltip needs without depending on
     * listRecent()'s bounded window, which a media item's download job can
     * easily fall out of by the time someone looks at a long-failed item.
     *
     * @param list<string> $mediaItemIds
     *
     * @return array<string, string> lastError keyed by media item id
     */
    public function latestDownloadFailureByMediaItem(array $mediaItemIds): array
    {
        if ($mediaItemIds === []) {
            return [];
        }

        // createdAt is second-precision, so a retry issued within the same
        // second as the failure it's replacing would tie -- id (a ULID) is
        // monotonic and breaks the tie in actual creation order.
        $jobs = JobRecord::select()
            ->where('entityType = ? AND intent = ?', 'media_item', JobIntent::Download)
            ->whereIn('entityId', $mediaItemIds)
            ->orderBy('createdAt', Direction::DESC)
            ->orderBy('id', Direction::DESC)
            ->all();

        $latestByMediaItem = [];
        foreach ($jobs as $job) {
            $latestByMediaItem[(string) $job->entityId] ??= $job;
        }

        $failures = [];
        foreach ($latestByMediaItem as $mediaItemId => $job) {
            if ($job->state === JobState::Failed && $job->lastError !== null) {
                $failures[$mediaItemId] = $job->lastError;
            }
        }

        return $failures;
    }
}
