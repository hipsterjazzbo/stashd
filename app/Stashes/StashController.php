<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Stashes\Api\StashInputResource;
use App\Stashes\Api\StashItemResource;
use App\Stashes\Api\StashResource;
use App\Support\PrefixedUlid;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Get;
use Tempest\Router\WithMiddleware;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class StashController
{
    public function __construct(
        private StashRepository $stashes,
        private StashItemRepository $stashItems,
        private StashInputRepository $stashInputs,
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

    private function notFound(string $message): Json
    {
        return new Json([
            'error' => [
                'code' => 'not_found',
                'message' => $message,
            ],
        ], Status::NOT_FOUND);
    }
}
