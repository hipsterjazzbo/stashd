<?php

declare(strict_types=1);

namespace App\System\Event;

use App\Support\PrefixedUlidGenerator;
use RuntimeException;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Query;

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

    /**
     * SQLite's implicit rowid (monotonic, native int) is used as the SSE
     * cursor instead of the ULID `id` column -- it maps directly onto
     * ServerSentMessage's int-typed `id`, which the browser echoes back as
     * the `Last-Event-ID` header on reconnect. No migration needed: this
     * table has never been declared WITHOUT ROWID.
     *
     * @return list<EventNotification>
     */
    public function listSinceSequence(?int $afterSequence, int $limit = 100): array
    {
        $sql = 'SELECT rowid AS sequence, id, eventType, payload, createdAt FROM event_notifications';
        $bindings = [];

        if ($afterSequence !== null) {
            $sql .= ' WHERE rowid > ?';
            $bindings[] = $afterSequence;
        }

        $sql .= ' ORDER BY rowid ASC LIMIT ?';
        $bindings[] = $limit;

        $rows = new Query($sql, $bindings)->fetch();

        return array_values(array_map($this->mapNotificationRow(...), $rows));
    }

    private function mapNotificationRow(mixed $row): EventNotification
    {
        if (! is_array($row)) {
            throw new RuntimeException('Unexpected event_notifications row shape.');
        }

        $sequence = $row['sequence'] ?? null;
        $id = $row['id'] ?? null;
        $eventType = $row['eventType'] ?? null;
        $payload = $row['payload'] ?? null;
        $createdAt = $row['createdAt'] ?? null;

        if (
            (! is_int($sequence) && ! is_string($sequence))
            || ! is_string($id)
            || ! is_string($eventType)
            || ! is_string($payload)
            || ! is_string($createdAt)
        ) {
            throw new RuntimeException('Unexpected event_notifications row shape.');
        }

        $decodedPayload = json_decode($payload, associative: true);

        if (! is_array($decodedPayload)) {
            throw new RuntimeException('event_notifications payload was not valid JSON.');
        }

        $stringKeyedPayload = [];

        foreach ($decodedPayload as $key => $value) {
            $stringKeyedPayload[(string) $key] = $value;
        }

        return new EventNotification(
            sequence: (int) $sequence,
            id: $id,
            eventType: $eventType,
            payload: $stringKeyedPayload,
            createdAt: DateTime::parse($createdAt, Timezone::UTC),
        );
    }

    /**
     * The cursor a brand-new connection (no Last-Event-ID) should start
     * from -- "now", not the beginning of the table. Without this, a fresh
     * page load on a long-running instance would spend its whole connection
     * window replaying old backlog instead of ever reaching live events.
     */
    public function latestSequence(): int
    {
        $row = new Query('SELECT MAX(rowid) AS sequence FROM event_notifications')->fetchFirst();
        $sequence = is_array($row) ? ($row['sequence'] ?? null) : null;

        return (is_int($sequence) || is_string($sequence)) ? (int) $sequence : 0;
    }
}
