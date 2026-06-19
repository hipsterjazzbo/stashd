<?php

declare(strict_types=1);

namespace App\Vault;

use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Support\PrefixedUlid;
use App\Vault\Api\AssetResource;
use App\Vault\Api\MediaItemResource;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Get;
use Tempest\Router\WithMiddleware;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class MediaItemController
{
    public function __construct(
        private MediaItemRepository $mediaItems,
        private AssetRepository $assets,
    ) {
    }

    #[Get('/api/v1/items')]
    public function index(): Json
    {
        return new Json([
            'items' => array_map(
                static fn ($item): array => MediaItemResource::fromRecord($item)->toArray(),
                $this->mediaItems->list(),
            ),
        ]);
    }

    #[Get('/api/v1/items/{id}')]
    public function show(string $id): Json
    {
        $item = $this->mediaItems->find(PrefixedUlid::parse($id));

        if ($item === null) {
            return new Json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Media item not found.',
                ],
            ], Status::NOT_FOUND);
        }

        return new Json([
            'item' => MediaItemResource::fromRecord($item)->toArray(),
        ]);
    }

    #[Get('/api/v1/items/{id}/assets')]
    public function assets(string $id): Json
    {
        $item = $this->mediaItems->find(PrefixedUlid::parse($id));

        if ($item === null) {
            return new Json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Media item not found.',
                ],
            ], Status::NOT_FOUND);
        }

        return new Json([
            'assets' => array_map(
                static fn ($asset): array => AssetResource::fromRecord($asset)->toArray(),
                $this->assets->listForMediaItem(PrefixedUlid::parse($id)),
            ),
        ]);
    }
}
