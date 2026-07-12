<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Providers\Core\DiscoveredItem;
use App\Providers\MetadataStrategyHandler;
use App\Providers\ProviderDates;
use App\Providers\ProviderException;
use App\Providers\ProviderHttpClient;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;
use Tempest\Support\Json\Exception\JsonCouldNotBeDecoded;

use function Tempest\Support\Json\decode;
use function Tempest\Support\str;

final readonly class YouTubeDataApiMetadataStrategy implements MetadataStrategyHandler
{
    public const string STRATEGY_KEY = 'youtube.data_api';

    public function __construct(
        private YouTubeDataApiKeyResolver $dataApiKey,
        private ProviderHttpClient $http,
    ) {
    }

    public function strategyKey(): string
    {
        return self::STRATEGY_KEY;
    }

    public function enrich(ResolvedInput $input, DiscoveredItem $item): DiscoveredItem
    {
        if (! $this->dataApiKey->hasKey()) {
            throw new ProviderException('YouTube Data API key is not configured.', 'data_api_unavailable');
        }

        $response = $this->http->get(YouTubeUris::dataApiVideo(
            $item->providerItemId,
            (string) $this->dataApiKey->key(),
        ));

        if (! $response->isSuccessful()) {
            throw new ProviderException('YouTube Data API metadata request failed.', 'data_api_fetch_failed', $response->statusCode);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = decode($response->body);
        } catch (JsonCouldNotBeDecoded $exception) {
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
        $description = is_string($snippet['description'] ?? null) && str($snippet['description'])->trim()->isNotEmpty()
            ? str($snippet['description'])->trim()->toString()
            : $item->description;
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
            $parsed = YouTubeDurations::parseIso8601($entry['contentDetails']['duration']);
            $durationSeconds = $parsed !== null ? (int) $parsed->getTotalSeconds() : $durationSeconds;
        }

        return new DiscoveredItem(
            providerItemId: $item->providerItemId,
            canonicalUri: $item->canonicalUri,
            title: $title,
            description: $description,
            durationSeconds: $durationSeconds,
            publishedAt: $publishedAt,
            thumbnailUri: $thumbnailUri,
            rawMetadata: [
                ...($item->rawMetadata ?? []),
                'data_api' => $payload,
            ],
        );
    }
}
