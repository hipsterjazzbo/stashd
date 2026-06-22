<?php

declare(strict_types=1);

namespace App\System\Event;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;

/**
 * One open `/api/v1/events` SSE connection's reservation slot.
 *
 * RoadRunner worker processes share no PHP memory, so this table (rather than
 * an in-process counter) is what lets SseConnectionRepository cap concurrent
 * streams across the whole pool. `updatedAt` doubles as a heartbeat: a row
 * whose connection died without releasing its slot (worker crash, killed
 * connection) ages past `staleAfter` and stops counting toward the cap.
 */
#[Table(name: 'sse_connections')]
final class SseConnectionRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public ?DateTime $createdAt = null;

    public ?DateTime $updatedAt = null;
}
