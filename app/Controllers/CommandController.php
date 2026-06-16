<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Command\CommandType;
use App\Domain\Support\PrefixedUlid;
use App\Http\Api\ApiJson;
use App\Http\Api\ApiResourceMapper;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\Auth\AuthContext;
use App\Services\Command\CommandDispatchService;
use App\Services\Command\InvalidCommandPayload;
use Tempest\Http\Request;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Get;
use Tempest\Router\Post;
use Tempest\Router\WithMiddleware;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class CommandController
{
    public function __construct(
        private CommandDispatchService $dispatch,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private AuthContext $context,
    ) {
    }

    #[Post('/api/v1/commands')]
    public function create(Request $request): Json
    {
        $body = ApiJson::normalizeRequest($request->body);
        $typeRaw = trim((string) ($body['type'] ?? ''));

        if ($typeRaw === '') {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'type is required.',
                ],
            ], Status::BAD_REQUEST);
        }

        $type = CommandType::tryFrom($typeRaw);

        if ($type === null) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Unsupported command type.',
                ],
            ], Status::BAD_REQUEST);
        }

        $options = is_array($body['options'] ?? null) ? $body['options'] : [];

        try {
            $result = $this->dispatch->dispatch($type, $options, $this->context->user());
        } catch (InvalidCommandPayload $exception) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => $exception->getMessage(),
                ],
            ], Status::BAD_REQUEST);
        }

        return new Json(ApiJson::encode($result->toArray()), Status::CREATED);
    }

    #[Get('/api/v1/commands/{id}')]
    public function show(string $id): Json
    {
        $command = $this->commands->find(PrefixedUlid::parse($id));

        if ($command === null) {
            return new Json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Command not found.',
                ],
            ], Status::NOT_FOUND);
        }

        return new Json([
            'command' => ApiResourceMapper::command($command),
            'jobs' => array_map(
                static fn ($job): array => ApiResourceMapper::job($job),
                $this->jobs->listForCommand((string) $command->id),
            ),
        ]);
    }
}
