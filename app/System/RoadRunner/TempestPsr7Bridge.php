<?php

declare(strict_types=1);

namespace App\System\RoadRunner;

use App\Auth\AuthContext;
use App\System\Boot\SqliteConfigurator;
use Generator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Tempest\Container\Container;
use Tempest\Database\Config\SQLiteConfig;
use Tempest\Http\Cookie\CookieManager;
use Tempest\Http\HttpRequestFailed;
use Tempest\Http\Response;
use Tempest\Http\Responses\EventStream;
use Tempest\Http\Status;
use Tempest\Router\Router;
use Tempest\View\View;
use Tempest\View\ViewRenderer;

/**
 * Bridges RoadRunner's PSR-7 worker loop to Tempest's HTTP router.
 *
 * Tempest ships HttpApplication::run() for traditional SAPI (one request per
 * process). RoadRunner keeps PHP workers alive and delivers many PSR-7 requests
 * over stdin/stdout. There is no official Tempest RoadRunner integration yet
 * (see tempestphp/tempest-framework#2011), so this adapter is intentional
 * framework glue — not domain code.
 *
 * Replace or delete this class when/if Tempest adds a first-party RR driver.
 *
 * @see docs/runtime/roadrunner.md
 */
final class TempestPsr7Bridge
{
    // PSR7Worker::chunkSize is global to the worker (read once per respond()
    // call), but only EventStream responses need true incremental flushing —
    // run() sets this only immediately before responding to one, and resets
    // to 0 (disabled) for every other request, so the change is scoped to SSE
    // and every other response stays byte-for-byte on the non-chunked path.
    // The value just needs to comfortably fit one formatted SSE message;
    // read() never waits to fill it (see GeneratorEventStream).
    private const int SSE_CHUNK_SIZE = 4096;

    // Raw byte chunk size for streamed file bodies (e.g. podcast episodes).
    // Larger than the SSE size because there's no per-message latency concern --
    // this just bounds how much of the file is held in memory at once, keeping
    // a multi-hundred-MB episode well under the worker's max_worker_memory cap.
    private const int FILE_CHUNK_SIZE = 1_048_576;

    public function __construct(
        private Router $router,
        private AuthContext $authContext,
        private ViewRenderer $viewRenderer,
        private SqliteConfigurator $sqlite,
        private SQLiteConfig $sqliteConfig,
        private Container $container,
    ) {
    }

    public static function create(string $root, ?string $internalStorage): self
    {
        $container = \Tempest\Core\Tempest::boot($root, [], $internalStorage);

        return $container->get(self::class);
    }

    public function run(): never
    {
        // stashd:boot (docker/entrypoint.sh) sets busy_timeout on its own
        // throwaway CLI connection, which doesn't carry over: each of these
        // long-lived RoadRunner worker processes opens its own PDO connection
        // (.rr.yaml pool.num_workers) that otherwise defaults to a 0ms SQLite
        // busy_timeout. PDO's default ERRMODE_SILENT means a SQLITE_BUSY hit
        // under concurrent requests doesn't throw — it just makes the query
        // look like "not found" (e.g. a valid session token appearing to not
        // exist), so this must be set per-worker, not just at container boot.
        $this->sqlite->configure($this->sqliteConfig);

        $factory = new Psr17Factory();
        $worker = Worker::create();
        $psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

        while (true) {
            $request = $psr7->waitRequest();

            if ($request === null) {
                break;
            }

            // RoadRunner does not populate PHP's $_COOKIE superglobal, but
            // Tempest's request mapper reads cookies from it (and decrypts
            // them). Seed it per request from the PSR-7 cookie params so the
            // session cookie is readable; reset in finally to avoid leaking
            // cookies between requests in this long-lived worker. CookieManager
            // is unregistered in finally for the same reason: it's a Tempest
            // #[Singleton], so without that, every cookie ever queued on this
            // worker (including stale delete-cookie instructions from a single
            // decryption failure) gets re-sent, re-encrypted, on every future
            // response from this worker.
            $_COOKIE = $request->getCookieParams();
            $psr7->chunkSize = 0;

            try {
                $response = $this->router->dispatch($request);
                // chunkSize > 0 makes PSR7Worker::respond() stream the body
                // incrementally instead of draining it to one string: SSE needs
                // it for per-message latency, streamed file bodies (Generator,
                // but not an EventStream) need it to avoid buffering a whole
                // episode in memory. Every other response stays on the
                // non-chunked path.
                $psr7->chunkSize = match (true) {
                    $response instanceof EventStream => self::SSE_CHUNK_SIZE,
                    $response->body instanceof Generator => self::FILE_CHUNK_SIZE,
                    default => 0,
                };
                $psr7->respond($this->toPsr7($factory, $response));
            } catch (HttpRequestFailed $failed) {
                if ($failed->cause instanceof Response) {
                    $psr7->respond($this->toPsr7($factory, $failed->cause));
                } else {
                    $psr7->respond($factory->createResponse($failed->status->value)->withBody(
                        $factory->createStream($failed->getMessage()),
                    ));
                }
            } catch (\Throwable $throwable) {
                $message = trim($throwable->getMessage()) !== ''
                    ? $throwable->getMessage()
                    : $throwable::class;
                $psr7->getWorker()->error($message);
                $psr7->respond($factory->createResponse(500)->withBody(
                    $factory->createStream($message),
                ));
            } finally {
                $this->authContext->set(null);
                $_COOKIE = [];
                $this->container->unregister(CookieManager::class);
            }
        }

        exit(0);
    }

    /**
     * Converts Tempest's native Response objects into PSR-7 for RoadRunner.
     *
     * Temporary until Tempest exposes a shared response serializer for
     * long-lived workers.
     */
    private function toPsr7(Psr17Factory $factory, Response $response): \Psr\Http\Message\ResponseInterface
    {
        $psr = $factory->createResponse($response->status->value);

        foreach ($response->headers as $header) {
            foreach ($header->values as $value) {
                $psr = $psr->withAddedHeader($header->name, $value);
            }
        }

        $body = $response->body;
        if (is_array($body)) {
            $psr = $psr->withBody($factory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        } elseif (is_string($body)) {
            $psr = $psr->withBody($factory->createStream($body));
        } elseif ($body instanceof View) {
            // Tempest::GenericResponseSender renders View bodies the same way
            // but never sets a Content-Type (it relies on the SAPI default);
            // RoadRunner's PSR-7 response has no such default, so set it here.
            if (! $psr->hasHeader('Content-Type')) {
                $psr = $psr->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            $psr = $psr->withBody($factory->createStream($this->viewRenderer->render($body)));
        } elseif ($body instanceof \JsonSerializable) {
            $psr = $psr->withBody($factory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        } elseif ($body instanceof Generator) {
            // Two kinds of generator body, both streamed incrementally (chunkSize
            // set in run()): an EventStream (app/System/Event/EventsController.php)
            // yields ServerSentMessage instances from a sleep-and-poll loop and is
            // framed as SSE by GeneratorEventStream; any other generator body
            // yields raw byte chunks (e.g. PodcastEpisodeController streaming an
            // episode file) and is forwarded verbatim by GeneratorFileStream.
            // Either way PSR7Worker::respond() flushes each chunk as produced
            // instead of draining the whole generator into one string -- which for
            // a large episode would blow the worker's memory cap.
            $psr = $psr->withBody(
                $response instanceof EventStream
                    ? new GeneratorEventStream($body)
                    : new GeneratorFileStream($body),
            );
        }

        if ($psr->getBody()->getSize() === 0 && $response->status !== Status::NO_CONTENT) {
            $psr = $psr->withBody($factory->createStream(''));
        }

        return $psr;
    }
}
