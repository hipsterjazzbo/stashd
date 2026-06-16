<?php

declare(strict_types=1);

namespace App\Infrastructure\RoadRunner;

use App\Services\Auth\AuthContext;
use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Tempest\Http\HttpRequestFailed;
use Tempest\Http\Response;
use Tempest\Http\Status;
use Tempest\Router\Router;

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
    public function __construct(
        private Router $router,
        private AuthContext $authContext,
    ) {
    }

    public static function create(string $root, ?string $internalStorage): self
    {
        $container = \Tempest\Core\Tempest::boot($root, [], $internalStorage);

        return $container->get(self::class);
    }

    public function run(): never
    {
        $factory = new Psr17Factory();
        $worker = Worker::create();
        $psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

        while (true) {
            $request = $psr7->waitRequest();

            if ($request === null) {
                break;
            }

            try {
                $response = $this->router->dispatch($request);
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
        } elseif ($body instanceof \JsonSerializable) {
            $psr = $psr->withBody($factory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        if ($psr->getBody()->getSize() === 0 && $response->status !== Status::NO_CONTENT) {
            $psr = $psr->withBody($factory->createStream(''));
        }

        return $psr;
    }
}
