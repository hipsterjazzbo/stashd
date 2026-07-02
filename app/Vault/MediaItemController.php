<?php

declare(strict_types=1);

namespace App\Vault;

use App\Broadcasts\Api\BroadcastResource;
use App\Broadcasts\BroadcastItemRepository;
use App\Broadcasts\BroadcastRepository;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Stashes\Api\StashResource;
use App\Stashes\StashId;
use App\Stashes\StashItemRepository;
use App\Stashes\StashRepository;
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
        private StashItemRepository $stashItems,
        private StashRepository $stashes,
        private BroadcastItemRepository $broadcastItems,
        private BroadcastRepository $broadcasts,
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
        $item = $this->findMediaItem($id);

        if ($item === null) {
            return $this->notFound();
        }

        return new Json([
            'item' => MediaItemResource::fromRecord($item)->toArray(),
        ]);
    }

    #[Get('/api/v1/items/{id}/assets')]
    public function assets(string $id): Json
    {
        $mediaItem = $this->findMediaItem($id);

        if ($mediaItem === null) {
            return $this->notFound();
        }

        $mediaItemId = MediaItemId::parse((string) $mediaItem->id);
        $assets = $this->assets->listForMediaItem($mediaItemId);

        $vaultOriginal = $this->assets->findByMediaItemAndRole($mediaItemId, AssetRole::VaultOriginal);
        $vaultOriginalReady = $vaultOriginal?->state === AssetState::Ready;

        $broadcastNamesById = [];
        foreach ($assets as $asset) {
            if ($asset->broadcastId === null || array_key_exists($asset->broadcastId, $broadcastNamesById)) {
                continue;
            }

            $broadcastNamesById[$asset->broadcastId] = $this->broadcasts->find(PrefixedUlid::parse($asset->broadcastId))?->name;
        }

        return new Json([
            'assets' => array_map(
                fn ($asset): array => AssetResource::fromRecord(
                    $asset,
                    AssetRegenerationGuidance::forAsset(
                        asset: $asset,
                        broadcastName: $asset->broadcastId === null ? null : $broadcastNamesById[$asset->broadcastId],
                        vaultOriginalReady: $vaultOriginalReady,
                        mediaItemUpstreamState: $mediaItem->upstreamState,
                    ),
                )->toArray(),
                $assets,
            ),
        ]);
    }

    /** Which stashes contain this media item — no back-reference existed before T12. */
    #[Get('/api/v1/items/{id}/stashes')]
    public function stashes(string $id): Json
    {
        $mediaItem = $this->findMediaItem($id);

        if ($mediaItem === null) {
            return $this->notFound();
        }

        $mediaItemId = MediaItemId::parse((string) $mediaItem->id);

        $stashIds = array_values(array_unique(array_map(
            static fn ($stashItem): string => (string) $stashItem->stashId,
            $this->stashItems->listForMediaItem($mediaItemId),
        )));

        $stashes = array_filter(array_map(
            fn (string $stashId) => $this->stashes->find(StashId::parse($stashId)),
            $stashIds,
        ));

        return new Json([
            'stashes' => array_map(
                static fn ($stash): array => StashResource::fromRecord($stash)->toArray(),
                array_values($stashes),
            ),
        ]);
    }

    /** Which broadcasts include this media item — no back-reference existed before T12. */
    #[Get('/api/v1/items/{id}/broadcasts')]
    public function broadcasts(string $id): Json
    {
        $mediaItem = $this->findMediaItem($id);

        if ($mediaItem === null) {
            return $this->notFound();
        }

        $mediaItemId = MediaItemId::parse((string) $mediaItem->id);

        $broadcastIds = array_values(array_unique(array_map(
            static fn ($broadcastItem): string => $broadcastItem->broadcastId,
            $this->broadcastItems->listForMediaItem($mediaItemId),
        )));

        $broadcasts = array_filter(array_map(
            fn (string $broadcastId) => $this->broadcasts->find(PrefixedUlid::parse($broadcastId)),
            $broadcastIds,
        ));

        return new Json([
            'broadcasts' => array_map(
                static fn ($broadcast): array => BroadcastResource::fromRecord($broadcast)->toArray(),
                array_values($broadcasts),
            ),
        ]);
    }

    private function findMediaItem(string $id): ?MediaItemRecord
    {
        return MediaItemId::isValid($id) ? $this->mediaItems->find(MediaItemId::parse($id)) : null;
    }

    private function notFound(): Json
    {
        return new Json([
            'error' => [
                'code' => 'not_found',
                'message' => 'Media item not found.',
            ],
        ], Status::NOT_FOUND);
    }
}
