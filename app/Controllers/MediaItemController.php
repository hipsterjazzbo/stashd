<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Support\PrefixedUlid;
use App\Http\Api\ApiResourceMapper;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Infrastructure\Persistence\AssetRepository;
use App\Infrastructure\Persistence\MediaItemRepository;
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
            'item' => ApiResourceMapper::mediaItem($item),
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
                static fn ($asset): array => ApiResourceMapper::asset($asset),
                $this->assets->listForMediaItem(PrefixedUlid::parse($id)),
            ),
        ]);
    }
}
