<?php

declare(strict_types=1);

namespace App\Domain\Provider\YouTube;

use App\Config\YouTubeConfig;
use App\Domain\Provider\DiscoveredItem;
use App\Domain\Provider\MetadataStrategyHandler;
use App\Domain\Provider\ProviderDates;
use App\Domain\Provider\ProviderException;
use App\Domain\Provider\ProviderHttpClient;
use App\Domain\Provider\ResolvedInput;
use App\Domain\Provider\StashdUri;

use function Tempest\Support\str;

final readonly class YouTubeDataApiMetadataStrategy implements MetadataStrategyHandler
{
    public const string STRATEGY_KEY = 'youtube.data_api';

    public function __construct(
        private YouTubeConfig $config,
        private ProviderHttpClient $http,
    ) {
    }

    public function strategyKey(): string
    {
        return self::STRATEGY_KEY;
    }

    public function enrich(ResolvedInput $input, DiscoveredItem $item): DiscoveredItem
    {
        if (! $this->config->hasDataApiKey()) {
            throw new ProviderException('YouTube Data API key is not configured.', 'data_api_unavailable');
        }

        $response = $this->http->get(YouTubeUris::dataApiVideo(
            $item->providerItemId,
            (string) $this->config->dataApiKey,
        ));

        if (! $response->isSuccessful()) {
            throw new ProviderException('YouTube Data API metadata request failed.', 'data_api_fetch_failed', $response->statusCode);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ProviderException('YouTube Data API response was invalid JSON.', 'data_api_parse_failed', previous: $exception);
        }

        $entry = $payload['items'][0] ?? null;

        if (! is_array($entry)) {
            throw new ProviderException('YouTube video metadata was not found.', 'data_api_not_found', 404);
        }

        /** @var array<string, mixed> $snippet */
        $snippet = is_array($entry['snippet'] ?? null) ? $entry['snippet'] : [];
        $title = is_string($snippet['title'] ?? null) && str($snippet['title'])->trim()->isNotEmpty()
            ? str($snippet['title'])->trim()->toString()
            : $item->title;
        $publishedAt = is_string($snippet['publishedAt'] ?? null)
            ? ProviderDates::tryParse($snippet['publishedAt'])
            : $item->publishedAt;
        $thumbnailUri = $item->thumbnailUri;

        if (isset($snippet['thumbnails']) && is_array($snippet['thumbnails'])) {
            foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
                if (isset($snippet['thumbnails'][$size]['url']) && is_string($snippet['thumbnails'][$size]['url'])) {
                    $thumbnailUri = StashdUri::parse($snippet['thumbnails'][$size]['url']);
                    break;
                }
            }
        }

        $durationSeconds = $item->durationSeconds;
        if (isset($entry['contentDetails']['duration']) && is_string($entry['contentDetails']['duration'])) {
            $durationSeconds = self::parseIso8601Duration($entry['contentDetails']['duration']) ?? $durationSeconds;
        }

        return new DiscoveredItem(
            providerItemId: $item->providerItemId,
            canonicalUri: $item->canonicalUri,
            title: $title,
            durationSeconds: $durationSeconds,
            publishedAt: $publishedAt,
            thumbnailUri: $thumbnailUri,
            rawMetadata: [
                ...($item->rawMetadata ?? []),
                'data_api' => $payload,
            ],
        );
    }

    private static function parseIso8601Duration(string $duration): ?int
    {
        if (! str($duration)->matches('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/')) {
            return null;
        }

        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
