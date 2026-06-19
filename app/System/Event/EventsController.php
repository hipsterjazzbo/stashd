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

    // RoadRunner drains this whole generator before writing a response, so a
    // worker is held for the full duration regardless of client disconnects
    // (confirmed via a "broken pipe" log entry with elapsed: 30148ms for the
    // old 30-iteration loop). With only a handful of HTTP workers, every page
    // that subscribes to this stream ties one up for that whole window, on
    // a tight ~POLL_INTERVAL_MS-spaced reconnect cycle for as long as the
    // page stays open — multiple such pages open at once can starve the pool
    // and make unrelated requests (including auth checks) wait or fail. Keep
    // this short; pair any increase with more `pool.num_workers` in .rr.yaml.
    private const int MAX_ITERATIONS = 10;

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
