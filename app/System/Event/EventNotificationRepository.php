<?php

declare(strict_types=1);

namespace App\System\Event;

use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
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
    public function publish(string $eventType, array $payload): EventNotificationRecord
    {
        $id = $this->ids->generate('evt')->toString();
        $record = new EventNotificationRecord(
            eventType: $eventType,
            payloadJson: json_encode($payload, JSON_THROW_ON_ERROR),
        );
        $record->id = new PrimaryKey($id);
        $record->createdAt = DateTime::now(Timezone::UTC);

        query(EventNotificationRecord::class)->insert($record)->execute();

        return EventNotificationRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist event notification.');
    }

    /** @return list<EventNotificationRecord> */
    public function listSinceId(?string $afterId, int $limit = 100): array
    {
        $query = EventNotificationRecord::select()->orderBy('createdAt', Direction::ASC);

        if ($afterId !== null) {
            $query = $query->where('id > ?', $afterId);
        }

        return $query->limit($limit)->all();
    }
}
