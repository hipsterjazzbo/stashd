<?php

declare(strict_types=1);

namespace App\System\Event;

use App\Http\Api\ApiJson;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use Tempest\DateTime\Duration;
use Tempest\Http\Responses\EventStream;
use Tempest\Http\ServerSentMessage;
use Tempest\Router\Get;
use Tempest\Router\WithMiddleware;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class EventsController
{
    private const int POLL_INTERVAL_MS = 1000;

    // A worker is held for this whole window regardless of client disconnects
    // (confirmed via a "broken pipe" log entry with elapsed: 30148ms for the
    // old 30-iteration loop) — RoadRunner PHP workers are one-process-one-
    // request, so even with App\System\RoadRunner\GeneratorEventStream's
    // incremental flushing (T18), the worker running this loop can't serve
    // any other request until it ends. With only a handful of HTTP workers,
    // every page that subscribes to this stream ties one up on a tight
    // ~POLL_INTERVAL_MS-spaced reconnect cycle for as long as the page stays
    // open. Keep this short; pair any increase with more `pool.num_workers`
    // in .rr.yaml and MAX_CONCURRENT_CONNECTIONS below.
    private const int MAX_ITERATIONS = 10;

    // Out of .rr.yaml's 8 workers, reserve at most half for SSE so unrelated
    // requests (including auth checks) always have workers free — the
    // starvation this fixes is exactly what forced num_workers 2 -> 4 -> 8
    // historically. A rejected connection gets a single retry-after message
    // instead of an error, so EventSource's own reconnect just waits and
    // retries — no special frontend handling needed.
    private const int MAX_CONCURRENT_CONNECTIONS = 4;

    private const int STALE_AFTER_SECONDS = 15;

    private const int REJECTED_RETRY_AFTER_SECONDS = 5;

    public function __construct(
        private EventNotificationRepository $notifications,
        private SseConnectionRepository $connections,
    ) {
    }

    #[Get('/api/v1/events')]
    public function stream(): EventStream
    {
        return new EventStream(function (): \Generator {
            $slot = $this->connections->tryAcquireSlot(self::MAX_CONCURRENT_CONNECTIONS, self::STALE_AFTER_SECONDS);

            if ($slot === null) {
                yield new ServerSentMessage(data: '', retryAfter: Duration::seconds(self::REJECTED_RETRY_AFTER_SECONDS));

                return;
            }

            try {
                $lastId = null;
                $iterations = 0;

                while ($iterations < self::MAX_ITERATIONS) {
                    foreach ($this->notifications->listSinceId($lastId) as $notification) {
                        $lastId = (string) $notification->id;
                        yield new ServerSentMessage(
                            data: ApiJson::encode([
                                'id' => $lastId,
                                'event' => $notification->eventType,
                                'payload' => $notification->payload,
                                'created_at' => $notification->createdAt?->toRfc3339(useZ: true),
                            ]),
                            event: $notification->eventType,
                        );
                    }

                    $iterations++;
                    $this->connections->heartbeat($slot);
                    usleep(self::POLL_INTERVAL_MS * 1000);
                }
            } finally {
                $this->connections->release($slot);
            }
        }, sleep: self::POLL_INTERVAL_MS);
    }
}
