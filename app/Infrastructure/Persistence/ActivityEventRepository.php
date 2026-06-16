<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Activity\ActivityEventRecord;
use App\Domain\Activity\ActivityLevel;
use App\Domain\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class ActivityEventRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        ActivityLevel $level,
        string $type,
        string $message,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $stashId = null,
        ?string $mediaItemId = null,
        ?string $broadcastId = null,
        ?string $jobId = null,
        ?string $commandId = null,
        ?string $groupKey = null,
        ?array $metadata = null,
    ): ActivityEventRecord {
        $id = $this->ids->generate('activity')->toString();
        $record = new ActivityEventRecord(
            level: $level,
            type: $type,
            message: $message,
            entityType: $entityType,
            entityId: $entityId,
            stashId: $stashId,
            mediaItemId: $mediaItemId,
            broadcastId: $broadcastId,
            jobId: $jobId,
            commandId: $commandId,
            groupKey: $groupKey,
            metadataJson: $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
        );
        $record->id = new PrimaryKey($id);
        $record->createdAt = RecordTimestamps::now();

        query(ActivityEventRecord::class)->insert($record)->execute();

        return ActivityEventRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist activity event.');
    }

    /** @return list<ActivityEventRecord> */
    public function listRecent(int $limit = 50): array
    {
        return ActivityEventRecord::select()
            ->orderBy('createdAt', Direction::DESC)
            ->limit($limit)
            ->all();
    }
}
