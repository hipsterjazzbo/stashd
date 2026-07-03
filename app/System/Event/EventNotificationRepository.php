<?php

declare(strict_types=1);

namespace App\System\Event;

use App\Support\PrefixedUlidGenerator;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class EventNotificationRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    /** @param array<string, mixed> $payload */
    /** @param array<string, mixed> $payload */
    public function publish(string $eventType, array $payload): EventNotificationRecord
    {
        $id = $this->ids->generate('evt')->toString();
        $record = new EventNotificationRecord(
            eventType: $eventType,
            payload: $payload,
        );
        $record->id = new PrimaryKey($id);
        $record->createdAt = DateTime::now(Timezone::UTC);

        query(EventNotificationRecord::class)->insert($record)->execute();

        return $record;
    }

    /** @return list<EventNotificationRecord> */
    public function listSinceId(?string $afterId, int $limit = 100): array
    {
        $query = EventNotificationRecord::select()
            ->whereNotNull('id')
            ->orderBy('createdAt', Direction::ASC);

        if ($afterId !== null) {
            $query = $query->where('id > ?', $afterId);
        }

        return $query->limit($limit)->all();
    }
}
