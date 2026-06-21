<?php

declare(strict_types=1);

namespace App\System\Event;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;

#[Table(name: 'event_notifications')]
final class EventNotificationRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $eventType,
        public string $payloadJson,
        public ?DateTime $createdAt = null,
    ) {
    }
}
