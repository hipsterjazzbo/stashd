<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Broadcasts\Podcasts\PodcastAssetSelector;
use App\Broadcasts\Podcasts\PodcastEpisodeByteRange;
use App\Broadcasts\Podcasts\PodcastMediaKind;
use App\Broadcasts\Podcasts\PodcastTokenService;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Vault\MediaItemId;
use Generator;
use SensitiveParameter;
use Tempest\Http\ContentType;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Http\Response;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Responses\Ok;
use Tempest\Http\Status;
use Tempest\Router\Get;

/**
 * Public, unauthenticated podcast episode media surface.
 *
 * Mirrors {@see PodcastFeedController}: the broadcast and item path tokens
 * are the only credential, so an unknown, mismatched, or revoked token must
 * be indistinguishable from an episode that never existed. This controller
 * serves only the Vault asset already selected by {@see PodcastAssetSelector}
 * for the matched broadcast item — it never resolves a filesystem path from
 * request input and never transcodes, remuxes, or extracts media.
 *
 * The router binds one path segment to one parameter, so the final
 * `episode.{ext}` segment is captured whole (e.g. `episode.mp3`) and split
 * here rather than in the route pattern.
 */
#[AllowApiClients]
final readonly class PodcastEpisodeController
{
    private const string EPISODE_FILENAME_PREFIX = 'episode.';

    private const int RANGE_READ_CHUNK_BYTES = 1_048_576;

    public function __construct(
        private PodcastTokenService $tokens,
        private PodcastAssetSelector $assets,
    ) {
    }

    #[Get('/b/{broadcastToken}/items/{itemToken}/{episodeFile}', without: [RequireAuthMiddleware::class])]
    public function episode(
        #[SensitiveParameter] string $broadcastToken,
        #[SensitiveParameter] string $itemToken,
        string $episodeFile,
        Request $request,
    ): Response {
        $ext = $this->extensionFromEpisodeFile($episodeFile);

        if ($ext === null) {
            return $this->notRevealed();
        }

        $broadcast = $this->tokens->findPodcastBroadcastByFeedToken($broadcastToken);

        if ($broadcast === null) {
            return $this->notRevealed();
        }

        $item = $this->tokens->findBroadcastItemByEpisodeToken($broadcast, $itemToken);

        if ($item === null) {
            return $this->notRevealed();
        }

        $mediaItemId = MediaItemId::parse($item->mediaItemId);
        $selection = match (PodcastMediaKind::forBroadcast($broadcast)) {
            PodcastMediaKind::Audio => $this->assets->audioAsset($mediaItemId),
            PodcastMediaKind::Video => $this->assets->videoAsset($mediaItemId),
        };

        // `{ext}` is a presentation hint only; the selected asset's own
        // extension is the source of truth, never the request path.
        if ($selection === null || $selection->extension !== $ext) {
            return $this->notRevealed();
        }

        $path = $selection->asset->path;

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return $this->notRevealed();
        }

        // Episodes are tens to hundreds of MB; the response body is always a
        // generator that reads the file in bounded chunks (never the whole span
        // at once), streamed by the RoadRunner bridge under chunkSize so it
        // never exceeds the worker's memory cap. HEAD must report the same
        // status/headers as the equivalent GET but carry no body, so it never
        // reads the file at all.
        $isHead = $request->method === Method::HEAD;

        $range = PodcastEpisodeByteRange::fromHeader($request->headers->get('Range'), $selection->length);

        if ($range->present && ! $range->satisfiable) {
            // Body must be a non-empty, non-string value: Tempest's error-response
            // pipeline only forwards a non-2xx response's own headers/body as-is
            // when they're truthy and not a plain string — otherwise it discards
            // them and rebuilds a generic error response, dropping Content-Range.
            return (new Ok(['error' => ['code' => 'range_not_satisfiable']]))
                ->setStatus(Status::RANGE_NOT_SATISFIABLE)
                ->addHeader('Content-Range', 'bytes */' . $selection->length);
        }

        if ($range->present) {
            return (new Ok($isHead ? null : $this->streamFile($path, $range->start, $range->length())))
                ->setStatus(Status::PARTIAL_CONTENT)
                ->addHeader(ContentType::HEADER, $selection->mimeType)
                ->addHeader('Accept-Ranges', 'bytes')
                ->addHeader('Content-Range', sprintf('bytes %d-%d/%d', $range->start, $range->end, $selection->length))
                ->addHeader('Content-Length', (string) $range->length());
        }

        return (new Ok($isHead ? null : $this->streamFile($path, 0, $selection->length)))
            ->addHeader(ContentType::HEADER, $selection->mimeType)
            ->addHeader('Accept-Ranges', 'bytes')
            ->addHeader('Content-Length', (string) $selection->length);
    }

    private function extensionFromEpisodeFile(string $episodeFile): ?string
    {
        if (! str_starts_with($episodeFile, self::EPISODE_FILENAME_PREFIX)) {
            return null;
        }

        $ext = substr($episodeFile, strlen(self::EPISODE_FILENAME_PREFIX));

        return $ext === '' ? null : $ext;
    }

    /**
     * Yields exactly `$length` bytes starting at `$start`, in bounded chunks
     * (never the whole span at once), so the RoadRunner bridge can stream the
     * file to the client without buffering it in the worker's memory. Stops
     * early on any I/O failure, including a short read caused by the file
     * changing after the asset was selected; the client then sees a truncated
     * body, which (with the Content-Length already sent) it treats as a failed
     * transfer — the same observable outcome as the old buffered path's
     * not-found, without first loading the file into memory.
     *
     * @return Generator<int, string>
     */
    private function streamFile(string $path, int $start, int $length): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            if ($start > 0 && fseek($handle, $start) !== 0) {
                return;
            }

            $remaining = $length;

            while ($remaining > 0) {
                $chunk = fread($handle, min(self::RANGE_READ_CHUNK_BYTES, $remaining));

                if ($chunk === false || $chunk === '') {
                    return;
                }

                yield $chunk;
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * A single non-revealing response for every failure mode (unknown
     * broadcast/item token, cross-broadcast item token, non-podcast
     * broadcast, unavailable asset, extension mismatch, unreadable file). It
     * carries no broadcast identity, item identity, token, or filesystem
     * path.
     */
    private function notRevealed(): NotFound
    {
        return new NotFound();
    }
}
