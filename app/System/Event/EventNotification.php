<?php

declare(strict_types=1);

namespace App\System\Event;

use Tempest\DateTime\DateTime;

final readonly class EventNotification
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $sequence,
        public string $id,
        public string $eventType,
        public array $payload,
        public DateTime $createdAt,
    ) {
    }
}
