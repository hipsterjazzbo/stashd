<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Broadcasts\Podcasts\PodcastAssetSelector;
use App\Broadcasts\Podcasts\PodcastTokenService;
use App\Config\StashdConfig;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use SensitiveParameter;
use Tempest\Http\ContentType;
use Tempest\Http\Response;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Responses\Ok;
use Tempest\Router\Get;

#[AllowApiClients]
final readonly class PodcastTranscriptController
{
    public function __construct(private PodcastTokenService $tokens, private PodcastAssetSelector $assets, private StashdConfig $config) {}
    #[Get('/b/{broadcastToken}/items/{itemToken}/transcript', without: [RequireAuthMiddleware::class])]
    public function transcript(#[SensitiveParameter] string $broadcastToken, #[SensitiveParameter] string $itemToken): Response
    {
        $broadcast = $this->tokens->findPodcastBroadcastByFeedToken($broadcastToken);
        $item = $broadcast === null ? null : $this->tokens->findBroadcastItemByEpisodeToken($broadcast, $itemToken);
        $asset = $item === null ? null : $this->assets->captionAsset($item->mediaItemId);
        $path = $asset?->path; $root = rtrim($this->config->vaultPath(), '/') . '/';
        if ($path === null || ! is_file($path) || ! is_readable($path) || ! str_starts_with($path, $root)) return new NotFound();
        $relative = substr($path, strlen($root));
        return (new Ok())->addHeader(ContentType::HEADER, $asset->mimeType ?? 'text/vtt')->addHeader('X-Accel-Redirect', '/' . implode('/', array_map(rawurlencode(...), explode('/', $relative))));
    }
}
