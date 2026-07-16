<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Providers\Core\DiscoveredItem;
use App\Providers\DiscoveryStrategyHandler;
use App\Providers\ProviderDates;
use App\Providers\ProviderException;
use App\Providers\ProviderHttpClient;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;

use function Tempest\Support\Arr\chunk;
use function Tempest\Support\Json\decode;

use Tempest\Support\Json\Exception\JsonCouldNotBeDecoded;

final readonly class YouTubeDataApiDiscoveryStrategy implements DiscoveryStrategyHandler
{
    public const string STRATEGY_KEY = 'youtube.data_api_discovery';

    private const int VIDEOS_BATCH_SIZE = 50;

    public function __construct(
        private YouTubeDataApiKeyResolver $dataApiKey,
        private ProviderHttpClient $http,
    ) {
    }

    public function strategyKey(): string
    {
        return self::STRATEGY_KEY;
    }

    public function discover(ResolvedInput $input): array
    {
        if (! $this->dataApiKey->hasKey()) {
            throw new ProviderException('YouTube Data API key is not configured.', 'data_api_unavailable');
        }

        return match ($input->inputType) {
            YouTubeInputType::Channel->value => $this->discoverChannel($input->providerInputId),
            YouTubeInputType::Playlist->value => $this->discoverPlaylist($input->providerInputId, $this->playlistTitle($input->providerInputId)),
            YouTubeInputType::Video->value => $this->discoverVideo($input->providerInputId),
            default => throw new ProviderException("Unsupported YouTube input type: {$input->inputType}", 'unsupported_input_type'),
        };
    }

    /** @return list<DiscoveredItem> */
    private function discoverChannel(string $channelId): array
    {
        $uploadsPlaylistId = $this->resolveUploadsPlaylistId($channelId);

        return $this->discoverPlaylist($uploadsPlaylistId);
    }

    private function resolveUploadsPlaylistId(string $channelId): string
    {
        $payload = $this->fetchJson(YouTubeUris::dataApiChannelContentDetails($channelId, (string) $this->dataApiKey->key()));
        $channel = $this->firstItem($payload);
        $contentDetails = is_array($channel['contentDetails'] ?? null) ? $channel['contentDetails'] : [];
        $relatedPlaylists = is_array($contentDetails['relatedPlaylists'] ?? null) ? $contentDetails['relatedPlaylists'] : [];

        $playlistId = $relatedPlaylists['uploads'] ?? null;

        if (! is_string($playlistId) || $playlistId === '') {
            throw new ProviderException('YouTube channel uploads playlist was not found.', 'data_api_channel_not_found', 404);
        }

        return $playlistId;
    }

    /** @return list<DiscoveredItem> */
    private function discoverPlaylist(string $playlistId, ?string $inputTitle = null): array
    {
        /** @var list<array{videoId: string, title: string, description: ?string, publishedAt: ?string, thumbnailUri: ?string}> $entries */
        $entries = [];
        $pageToken = null;

        do {
            $payload = $this->fetchJson(YouTubeUris::dataApiPlaylistItems($playlistId, (string) $this->dataApiKey->key(), $pageToken));

            foreach (($payload['items'] ?? []) as $item) {
                $entry = $this->playlistItemToEntry($item);

                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }

            $pageToken = is_string($payload['nextPageToken'] ?? null) ? $payload['nextPageToken'] : null;
        } while ($pageToken !== null);

        return $this->buildDiscoveredItems($entries, $inputTitle);
    }

    private function playlistTitle(string $playlistId): ?string
    {
        $payload = $this->fetchJson(YouTubeUris::dataApiPlaylist($playlistId, (string) $this->dataApiKey->key()));
        $playlist = $this->firstItem($payload);
        $snippet = is_array($playlist['snippet'] ?? null) ? $playlist['snippet'] : [];
        $title = $snippet['title'] ?? null;

        return is_string($title) && trim($title) !== '' ? trim($title) : null;
    }

    /** @return list<DiscoveredItem> */
    private function discoverVideo(string $videoId): array
    {
        $payload = $this->fetchJson(YouTubeUris::dataApiVideosBatch([$videoId], (string) $this->dataApiKey->key()));
        $item = $this->firstItem($payload);

        if (! is_array($item)) {
            return [];
        }

        /** @var array<string, mixed> $snippet */
        $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
        $classification = $this->classify($item);
        $thumbnailUri = $this->bestThumbnailUri($snippet['thumbnails'] ?? null);

        return [new DiscoveredItem(
            providerItemId: $videoId,
            canonicalUri: YouTubeUris::watch($videoId),
            title: is_string($snippet['title'] ?? null) ? $snippet['title'] : $videoId,
            description: is_string($snippet['description'] ?? null) ? $snippet['description'] : null,
            durationSeconds: $classification['durationSeconds'],
            publishedAt: ProviderDates::tryParse(is_string($snippet['publishedAt'] ?? null) ? $snippet['publishedAt'] : null),
            thumbnailUri: $thumbnailUri !== null ? StashdUri::parse($thumbnailUri) : null,
            contentType: $classification['contentType'],
        )];
    }

    /**
     * @param mixed $item
     *
     * @return array{videoId: string, title: string, description: ?string, publishedAt: ?string, thumbnailUri: ?string}|null
     */
    private function playlistItemToEntry(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        /** @var array<string, mixed> $snippet */
        $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
        $videoId = $snippet['resourceId']['videoId'] ?? null;

        if (! is_string($videoId) || $videoId === '') {
            return null;
        }

        return [
            'videoId' => $videoId,
            'title' => is_string($snippet['title'] ?? null) ? $snippet['title'] : $videoId,
            'description' => is_string($snippet['description'] ?? null) ? $snippet['description'] : null,
            'publishedAt' => is_string($snippet['publishedAt'] ?? null) ? $snippet['publishedAt'] : null,
            'thumbnailUri' => $this->bestThumbnailUri($snippet['thumbnails'] ?? null),
        ];
    }

    /**
     * @param list<array{videoId: string, title: string, description: ?string, publishedAt: ?string, thumbnailUri: ?string}> $entries
     *
     * @return list<DiscoveredItem>
     */
    private function buildDiscoveredItems(array $entries, ?string $inputTitle = null): array
    {
        if ($entries === []) {
            return [];
        }

        $videoIds = array_values(array_unique(array_column($entries, 'videoId')));
        $classifications = $this->fetchClassifications($videoIds);

        return array_values(array_filter(array_map(
            function (array $entry) use ($classifications, $inputTitle): ?DiscoveredItem {
                $classification = $classifications[$entry['videoId']] ?? null;

                if ($classification === null) {
                    return null;
                }

                return new DiscoveredItem(
                    providerItemId: $entry['videoId'],
                    canonicalUri: YouTubeUris::watch($entry['videoId']),
                    title: $entry['title'],
                    description: $entry['description'],
                    durationSeconds: $classification['durationSeconds'],
                    publishedAt: ProviderDates::tryParse($entry['publishedAt']),
                    thumbnailUri: $entry['thumbnailUri'] !== null ? StashdUri::parse($entry['thumbnailUri']) : null,
                    rawMetadata: $inputTitle === null ? null : ['input_title' => $inputTitle],
                    contentType: $classification['contentType'],
                );
            },
            $entries,
        )));
    }

    /**
     * @param list<string> $videoIds
     *
     * @return array<string, array{durationSeconds: ?int, contentType: string}>
     */
    private function fetchClassifications(array $videoIds): array
    {
        $classifications = [];

        foreach (chunk($videoIds, self::VIDEOS_BATCH_SIZE) as $batch) {
            $payload = $this->fetchJson(YouTubeUris::dataApiVideosBatch(array_values($batch), (string) $this->dataApiKey->key()));

            foreach (($payload['items'] ?? []) as $item) {
                if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                    continue;
                }

                $classifications[$item['id']] = $this->classify($item);
            }
        }

        return $classifications;
    }

    /** @return array{durationSeconds: ?int, contentType: string} */
    private function classify(array $item): array
    {
        /** @var array<string, mixed> $snippet */
        $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
        /** @var array<string, mixed> $contentDetails */
        $contentDetails = is_array($item['contentDetails'] ?? null) ? $item['contentDetails'] : [];
        /** @var array<string, mixed>|null $liveStreamingDetails */
        $liveStreamingDetails = is_array($item['liveStreamingDetails'] ?? null) ? $item['liveStreamingDetails'] : null;

        $duration = is_string($contentDetails['duration'] ?? null) ? YouTubeDurations::parseIso8601($contentDetails['duration']) : null;
        $durationSeconds = $duration !== null ? (int) $duration->getTotalSeconds() : null;

        $liveBroadcastContent = $snippet['liveBroadcastContent'] ?? null;

        $contentType = match (true) {
            $liveBroadcastContent === 'live' => 'live',
            $liveBroadcastContent === 'upcoming' => 'premiere',
            $durationSeconds !== null && $durationSeconds <= 180 => 'short',
            default => 'regular',
        };

        return ['durationSeconds' => $durationSeconds, 'contentType' => $contentType];
    }

    private function bestThumbnailUri(mixed $thumbnails): ?string
    {
        if (! is_array($thumbnails)) {
            return null;
        }

        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
            if (isset($thumbnails[$size]['url']) && is_string($thumbnails[$size]['url'])) {
                return $thumbnails[$size]['url'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function firstItem(array $payload): ?array
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            return null;
        }

        $item = $items[0] ?? null;

        if (! is_array($item)) {
            return null;
        }

        $normalized = [];

        foreach ($item as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function fetchJson(\Tempest\Support\Uri\Uri $uri): array
    {
        $response = $this->http->get($uri);

        if (! $response->isSuccessful()) {
            throw new ProviderException('YouTube Data API request failed.', 'data_api_fetch_failed', $response->statusCode);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = decode($response->body);
        } catch (JsonCouldNotBeDecoded $exception) {
            throw new ProviderException('YouTube Data API response was invalid JSON.', 'data_api_parse_failed', previous: $exception);
        }

        return $payload;
    }
}
