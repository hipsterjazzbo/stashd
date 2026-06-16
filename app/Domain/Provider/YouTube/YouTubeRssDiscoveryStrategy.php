<?php

declare(strict_types=1);

namespace App\Domain\Provider\YouTube;

use App\Domain\Provider\DiscoveredItem;
use App\Domain\Provider\DiscoveryStrategyHandler;
use App\Domain\Provider\ProviderException;
use App\Domain\Provider\ProviderHttpClient;
use App\Domain\Provider\ResolvedInput;

final readonly class YouTubeRssDiscoveryStrategy implements DiscoveryStrategyHandler
{
    public const string STRATEGY_KEY = 'youtube.rss';

    public function __construct(
        private ProviderHttpClient $http,
        private YouTubeChannelIdResolver $channelIds,
        private YouTubeRssParser $parser,
        private YouTubeVideoDiscovery $videos,
    ) {
    }

    public function strategyKey(): string
    {
        return self::STRATEGY_KEY;
    }

    public function discover(ResolvedInput $input): array
    {
        return match ($input->inputType) {
            YouTubeInputType::Channel->value => $this->discoverChannel($input),
            YouTubeInputType::Playlist->value => $this->discoverPlaylist($input),
            YouTubeInputType::Video->value => $this->videos->discover($input),
            default => throw new ProviderException("Unsupported YouTube input type: {$input->inputType}", 'unsupported_input_type'),
        };
    }

    /** @return list<DiscoveredItem> */
    private function discoverChannel(ResolvedInput $input): array
    {
        $channelId = $this->channelIds->resolve($input->providerInputId);

        return $this->fetchFeed(YouTubeUris::channelFeed($channelId), 'channel');
    }

    /** @return list<DiscoveredItem> */
    private function discoverPlaylist(ResolvedInput $input): array
    {
        return $this->fetchFeed(YouTubeUris::playlistFeed($input->providerInputId), 'playlist');
    }

    /** @return list<DiscoveredItem> */
    private function fetchFeed(\Tempest\Support\Uri\Uri $feedUrl, string $feedKind): array
    {
        $response = $this->http->get($feedUrl);

        if ($response->statusCode === 404) {
            throw new ProviderException('YouTube feed is unavailable or private.', 'feed_unavailable', 404);
        }

        if (! $response->isSuccessful()) {
            throw new ProviderException(
                'YouTube RSS discovery failed.',
                'rss_fetch_failed',
                $response->statusCode,
            );
        }

        try {
            return $this->parser->parse($response->body, $feedKind);
        } catch (ProviderException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new ProviderException(
                'YouTube RSS response could not be parsed.',
                'rss_parse_failed',
                previous: $throwable,
            );
        }
    }
}
