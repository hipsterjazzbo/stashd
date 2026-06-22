<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Auth\AuthContext;
use App\Commands\CommandDispatchService;
use App\Commands\CommandType;
use App\Commands\InvalidCommandPayload;
use App\Http\Api\ApiJson;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Stashes\Api\StashInputResource;
use App\Stashes\Api\StashItemResource;
use App\Stashes\Api\StashResource;
use App\Support\PrefixedUlid;
use Tempest\Http\Request;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Delete;
use Tempest\Router\Get;
use Tempest\Router\Patch;
use Tempest\Router\Post;
use Tempest\Router\WithMiddleware;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class StashController
{
    public function __construct(
        private StashRepository $stashes,
        private StashItemRepository $stashItems,
        private StashInputRepository $stashInputs,
        private CommandDispatchService $dispatch,
        private AuthContext $context,
    ) {
    }

    #[Get('/api/v1/stashes')]
    public function index(): Json
    {
        return new Json([
            'stashes' => array_map(
                static fn ($stash): array => StashResource::fromRecord($stash)->toArray(),
                $this->stashes->list(),
            ),
        ]);
    }

    #[Post('/api/v1/stashes')]
    public function create(Request $request): Json
    {
        $body = ApiJson::normalizeRequest($request->body);

        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            $name = 'New Stash';
        }

        $syncMode = SyncMode::Automatic;
        if (isset($body['syncMode'])) {
            $syncMode = SyncMode::tryFrom((string) $body['syncMode']);
            if ($syncMode === null) {
                return $this->validationError('Unsupported sync_mode.');
            }
        }

        $downloadPolicy = DownloadPolicy::Video;
        if (isset($body['downloadPolicy'])) {
            $downloadPolicy = DownloadPolicy::tryFrom((string) $body['downloadPolicy']);
            if ($downloadPolicy === null) {
                return $this->validationError('Unsupported download_policy.');
            }
        }

        $organizationMode = OrganizationMode::Flat;
        if (isset($body['organizationMode'])) {
            $organizationMode = OrganizationMode::tryFrom((string) $body['organizationMode']);
            if ($organizationMode === null) {
                return $this->validationError('Unsupported organization_mode.');
            }
        }

        $stash = $this->stashes->create(
            name: $name,
            slug: $this->stashes->nextAvailableSlug($this->stashes->slugify($name)),
            syncMode: $syncMode,
            downloadPolicy: $downloadPolicy,
            organizationMode: $organizationMode,
            description: isset($body['description']) ? trim((string) $body['description']) : null,
        );

        return new Json([
            'stash' => StashResource::fromRecord($stash)->toArray(),
        ], Status::CREATED);
    }

    #[Get('/api/v1/stashes/{id}')]
    public function show(string $id): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($id));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        return new Json([
            'stash' => StashResource::fromRecord($stash)->toArray(),
        ]);
    }

    #[Get('/api/v1/stashes/{id}/items')]
    public function items(string $id): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($id));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        return new Json([
            'items' => array_map(
                static fn ($item): array => StashItemResource::fromRecord($item)->toArray(),
                $this->stashItems->listForStash(PrefixedUlid::parse($id)),
            ),
        ]);
    }

    #[Get('/api/v1/stashes/{id}/inputs')]
    public function inputs(string $id): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($id));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        return new Json([
            'inputs' => array_map(
                static fn ($input): array => StashInputResource::fromRecord($input)->toArray(),
                $this->stashInputs->listForStash(PrefixedUlid::parse($id)),
            ),
        ]);
    }

    #[Post('/api/v1/stashes/{id}/inputs')]
    public function addInput(string $id, Request $request): Json
    {
        if ($this->stashes->find(PrefixedUlid::parse($id)) === null) {
            return $this->notFound('Stash not found.');
        }

        $body = ApiJson::normalizeRequest($request->body);

        $options = [
            'stash_id' => $id,
            'preflight_command_id' => trim((string) ($body['preflightCommandId'] ?? '')),
            'options' => is_array($body['options'] ?? null) ? $body['options'] : [],
        ];

        try {
            $result = $this->dispatch->dispatch(
                CommandType::StashAddInput,
                $options,
                $this->context->user(),
            );
        } catch (InvalidCommandPayload $exception) {
            return $this->validationError($exception->getMessage());
        }

        return new Json(ApiJson::encode($result->toArray()), Status::CREATED);
    }

    #[Patch('/api/v1/stashes/{id}')]
    public function update(string $id, Request $request): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($id));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        $body = ApiJson::normalizeRequest($request->body);

        $name = null;

        if (isset($body['name'])) {
            $name = trim((string) $body['name']);

            if ($name === '') {
                return $this->validationError('name cannot be blank.');
            }
        }

        $syncMode = null;

        if (isset($body['syncMode'])) {
            $syncMode = SyncMode::tryFrom((string) $body['syncMode']);

            if ($syncMode === null) {
                return $this->validationError('Unsupported sync_mode.');
            }
        }

        $downloadPolicy = null;

        if (isset($body['downloadPolicy'])) {
            $downloadPolicy = DownloadPolicy::tryFrom((string) $body['downloadPolicy']);

            if ($downloadPolicy === null) {
                return $this->validationError('Unsupported download_policy.');
            }
        }

        $organizationMode = null;

        if (isset($body['organizationMode'])) {
            $organizationMode = OrganizationMode::tryFrom((string) $body['organizationMode']);

            if ($organizationMode === null) {
                return $this->validationError('Unsupported organization_mode.');
            }
        }

        $stash = $this->stashes->update(
            $stash,
            name: $name,
            description: isset($body['description']) ? trim((string) $body['description']) : null,
            syncMode: $syncMode,
            downloadPolicy: $downloadPolicy,
            organizationMode: $organizationMode,
        );

        return new Json([
            'stash' => StashResource::fromRecord($stash)->toArray(),
        ]);
    }

    #[Delete('/api/v1/stashes/{id}')]
    public function delete(string $id): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($id));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        $this->stashes->delete($stash);

        return new Json(['deleted' => true]);
    }

    #[Get('/api/v1/stashes/{id}/delete-impact')]
    public function deleteImpact(string $id): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($id));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        return new Json([
            'delete_impact' => ApiJson::encode($this->stashes->deleteImpact($stash)),
        ]);
    }

    private function notFound(string $message): Json
    {
        return new Json([
            'error' => [
                'code' => 'not_found',
                'message' => $message,
            ],
        ], Status::NOT_FOUND);
    }

    private function validationError(string $message): Json
    {
        return new Json([
            'error' => [
                'code' => 'validation_error',
                'message' => $message,
            ],
        ], Status::BAD_REQUEST);
    }
}
