<?php

declare(strict_types=1);

namespace App\System\Event;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use Tempest\Database\Connection\Connection;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class SseConnectionRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
        private Connection $connection,
    ) {
    }

    /**
     * Prunes connections that haven't heartbeated within `$staleAfterSeconds`,
     * then reserves a slot if fewer than `$maxConcurrent` live ones remain.
     *
     * The count-then-insert is done as one raw SQL statement (count
     * subquery gating an INSERT...SELECT), not Tempest's query builder
     * (count) followed by a separate insert: across concurrent RoadRunner
     * worker processes, two separate statements leave a race window where
     * both read the same "under capacity" count before either commits its
     * insert, letting more than `$maxConcurrent` slots through. SQLite holds
     * the write lock for a single statement's full duration — including its
     * subquery — so this can't happen within one INSERT...SELECT...WHERE.
     *
     * `$maxConcurrent` is interpolated as a literal, not bound: confirmed via
     * a throwaway test that PHP's PDO SQLite driver mis-evaluates this
     * specific shape (a parameter feeding the WHERE-clause comparison against
     * a scalar subquery) — every execute() of the prepared statement let the
     * row through regardless of the actual count, even though the identical
     * SQL with that one value as a literal (or with the same parameter bound
     * anywhere else, e.g. the SELECT list) behaved correctly. `$maxConcurrent`
     * is never user input (an internal tuning constant), so this is safe.
     *
     * @return PrefixedUlid|null the reserved slot's id, or null at capacity
     */
    public function tryAcquireSlot(int $maxConcurrent, int $staleAfterSeconds): ?PrefixedUlid
    {
        $staleBefore = DateTime::now(Timezone::UTC)->minusSeconds($staleAfterSeconds);

        foreach (SseConnectionRecord::select()->where('updatedAt < ?', $staleBefore)->all() as $stale) {
            $stale->delete();
        }

        $id = $this->ids->generate('sse')->toString();

        $statement = $this->connection->prepare(
            'INSERT INTO sse_connections (id, createdAt, updatedAt)
             SELECT :id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             WHERE (SELECT COUNT(*) FROM sse_connections) < ' . $maxConcurrent,
        );
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() !== 1) {
            return null;
        }

        return PrefixedUlid::parse($id);
    }

    public function heartbeat(PrefixedUlid $id): void
    {
        $record = SseConnectionRecord::findById($id->toPrimaryKey());

        if ($record === null) {
            return;
        }

        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();
    }

    public function release(PrefixedUlid $id): void
    {
        SseConnectionRecord::findById($id->toPrimaryKey())?->delete();
    }
}
