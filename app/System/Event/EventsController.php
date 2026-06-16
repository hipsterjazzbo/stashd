<?php

declare(strict_types=1);

namespace App\System\Event;

use App\Http\Api\ApiJson;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use Tempest\Http\Responses\EventStream;
use Tempest\Http\ServerSentMessage;
use Tempest\Router\Get;
use Tempest\Router\WithMiddleware;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class EventsController
{
    private const int POLL_INTERVAL_MS = 1000;

    private const int MAX_ITERATIONS = 30;

    public function __construct(
        private EventNotificationRepository $notifications,
    ) {
    }

    #[Get('/api/v1/events')]
    public function stream(): EventStream
    {
        return new EventStream(function (): \Generator {
            $lastId = null;
            $iterations = 0;

            while ($iterations < self::MAX_ITERATIONS) {
                foreach ($this->notifications->listSinceId($lastId) as $notification) {
                    $lastId = (string) $notification->id;
                    $payload = json_decode($notification->payloadJson, true, flags: JSON_THROW_ON_ERROR);

                    yield new ServerSentMessage(
                        data: ApiJson::encode([
                            'id' => $lastId,
                            'event' => $notification->eventType,
                            'payload' => is_array($payload) ? $payload : [],
                            'created_at' => $notification->createdAt,
                        ]),
                        event: $notification->eventType,
                    );
                }

                $iterations++;
                usleep(self::POLL_INTERVAL_MS * 1000);
            }
        }, sleep: self::POLL_INTERVAL_MS);
    }
}
