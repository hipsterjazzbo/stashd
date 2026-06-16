<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Http\Api\ApiJson;
use App\Http\Api\ApiResourceMapper;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Stashes\StashRepository;
use App\Support\PrefixedUlid;
use App\System\Storage\PathSanitizer;
use Tempest\Http\Request;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Get;
use Tempest\Router\Post;
use Tempest\Router\WithMiddleware;

use function Tempest\Support\str;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class BroadcastController
{
    public function __construct(
        private StashRepository $stashes,
        private BroadcastRepository $broadcasts,
        private BroadcastItemRepository $broadcastItems,
    ) {
    }

    #[Get('/api/v1/stashes/{stashId}/broadcasts')]
    public function index(string $stashId): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($stashId));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        return new Json([
            'broadcasts' => array_map(
                static fn ($broadcast): array => ApiResourceMapper::broadcast($broadcast),
                $this->broadcasts->listForStash(PrefixedUlid::parse($stashId)),
            ),
        ]);
    }

    #[Post('/api/v1/stashes/{stashId}/broadcasts')]
    public function create(string $stashId, Request $request): Json
    {
        $stash = $this->stashes->find(PrefixedUlid::parse($stashId));

        if ($stash === null) {
            return $this->notFound('Stash not found.');
        }

        $body = ApiJson::normalizeRequest($request->body);
        $typeRaw = trim((string) ($body['type'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        $slugRaw = trim((string) ($body['slug'] ?? ''));

        if ($typeRaw === '' || $name === '') {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'type and name are required.',
                ],
            ], Status::BAD_REQUEST);
        }

        $type = BroadcastType::tryFrom($typeRaw);

        if ($type === null) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Unsupported broadcast type.',
                ],
            ], Status::BAD_REQUEST);
        }

        $slug = $slugRaw !== '' ? $slugRaw : str($name)->slug()->toString();

        try {
            $slug = PathSanitizer::sanitizeSegment($slug);
        } catch (\InvalidArgumentException) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Invalid broadcast slug.',
                ],
            ], Status::BAD_REQUEST);
        }

        $stashUlid = PrefixedUlid::parse($stashId);

        if ($this->broadcasts->findByStashAndSlug($stashUlid, $slug) !== null) {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Broadcast slug already exists for this stash.',
                ],
            ], Status::BAD_REQUEST);
        }

        $settings = is_array($body['settings'] ?? null) ? ApiJson::encode($body['settings']) : [];

        $broadcast = $this->broadcasts->create(
            stashId: $stashUlid,
            type: $type,
            name: $name,
            slug: $slug,
            settings: $settings,
        );

        return new Json([
            'broadcast' => ApiResourceMapper::broadcast($broadcast),
        ], Status::CREATED);
    }

    #[Get('/api/v1/broadcasts/{id}')]
    public function show(string $id): Json
    {
        $broadcast = $this->broadcasts->find(PrefixedUlid::parse($id));

        if ($broadcast === null) {
            return $this->notFound('Broadcast not found.');
        }

        return new Json([
            'broadcast' => ApiResourceMapper::broadcast($broadcast),
        ]);
    }

    #[Get('/api/v1/broadcasts/{id}/items')]
    public function items(string $id): Json
    {
        $broadcast = $this->broadcasts->find(PrefixedUlid::parse($id));

        if ($broadcast === null) {
            return $this->notFound('Broadcast not found.');
        }

        return new Json([
            'items' => array_map(
                static fn ($item): array => ApiResourceMapper::broadcastItem($item),
                $this->broadcastItems->listForBroadcast(PrefixedUlid::parse($id)),
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
