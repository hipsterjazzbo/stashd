<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Config\YtdlpConfig;
use App\Downloads\Ytdlp\YtdlpGateway;
use App\Downloads\Ytdlp\YtdlpOptionsBuilder;
use App\Providers\Core\DiscoveredItem;
use App\Providers\DiscoveryStrategyHandler;
use App\Providers\ProviderDates;
use App\Providers\ProviderException;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;
use Tempest\Support\Uri\Uri;

/**
 * Full-channel/playlist discovery via yt-dlp's flat-playlist listing --
 * a fallback for when no YouTube Data API key is configured, since RSS
 * feeds cap at roughly the 15 most recent items. Only lists id/title per
 * entry (no per-video resolution), keeping this to one yt-dlp process call
 * regardless of channel size.
 */
final readonly class YouTubeYtdlpDiscoveryStrategy implements DiscoveryStrategyHandler
{
    public const string STRATEGY_KEY = 'youtube.ytdlp_discovery';

    public function __construct(
        private YtdlpConfig $config,
        private YtdlpGateway $gateway,
        private YtdlpOptionsBuilder $options,
        private YouTubeChannelIdResolver $channelIds,
    ) {
    }

    public function strategyKey(): string
    {
        return self::STRATEGY_KEY;
    }

    public function isAvailable(): bool
    {
        if (! $this->config->realDownloadsEnabled()) {
            return false;
        }

        return $this->gateway->probe()->available;
    }

    public function discover(ResolvedInput $input): array
    {
        if (! $this->isAvailable()) {
            throw new ProviderException('yt-dlp is not available for YouTube discovery.', 'ytdlp_discovery_unavailable');
        }

        return match ($input->inputType) {
            YouTubeInputType::Channel->value => $this->discoverChannel($input),
            YouTubeInputType::Playlist->value => $this->discoverPlaylist($input),
            default => throw new ProviderException(
                "Unsupported YouTube input type for ytdlp discovery: {$input->inputType}",
                'unsupported_input_type',
            ),
        };
    }

    /** @return list<DiscoveredItem> */
    private function discoverChannel(ResolvedInput $input): array
    {
        $channel = $this->channelIds->resolve($input->providerInputId);

        return $this->fetchFlatEntries(YouTubeUris::channelVideosPage($channel->id));
    }

    /** @return list<DiscoveredItem> */
    private function discoverPlaylist(ResolvedInput $input): array
    {
        return $this->fetchFlatEntries(YouTubeUris::playlistPage($input->providerInputId));
    }

    /** @return list<DiscoveredItem> */
    private function fetchFlatEntries(Uri $url): array
    {
        try {
            $playlist = $this->gateway->extractPlaylist(
                $url->toString(),
                sys_get_temp_dir(),
                $this->options->playlistOptions(),
            );
        } catch (\Throwable $throwable) {
            throw new ProviderException(
                'yt-dlp playlist discovery failed.',
                'ytdlp_discovery_failed',
                previous: $throwable,
            );
        }

        $entries = $playlist->raw['entries'] ?? null;

        if (! is_array($entries)) {
            return [];
        }

        $items = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $item = $this->mapEntry($entry);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @param array<mixed, mixed> $entry */
    private function mapEntry(array $entry): ?DiscoveredItem
    {
        $rawId = $entry['id'] ?? null;
        $videoId = is_string($rawId) ? trim($rawId) : '';

        if ($videoId === '') {
            return null;
        }

        $rawTitle = $entry['title'] ?? null;
        $title = is_string($rawTitle) && trim($rawTitle) !== '' ? trim($rawTitle) : "YouTube Video {$videoId}";

        $rawDuration = $entry['duration'] ?? null;
        $durationSeconds = is_numeric($rawDuration) ? (int) round((float) $rawDuration) : null;

        $uploadDate = $entry['upload_date'] ?? null;

        return new DiscoveredItem(
            providerItemId: $videoId,
            canonicalUri: YouTubeUris::watch($videoId),
            title: $title,
            durationSeconds: $durationSeconds,
            publishedAt: ProviderDates::tryParse(is_string($uploadDate) ? $uploadDate : null),
            thumbnailUri: $this->extractThumbnail($entry),
            rawMetadata: ['flat_entry' => $entry],
        );
    }

    /** @param array<mixed, mixed> $entry */
    private function extractThumbnail(array $entry): ?StashdUri
    {
        $thumbnails = $entry['thumbnails'] ?? null;

        if (! is_array($thumbnails) || $thumbnails === []) {
            return null;
        }

        $last = end($thumbnails);
        $url = is_array($last) ? ($last['url'] ?? null) : null;

        return is_string($url) && trim($url) !== '' ? StashdUri::parse(trim($url)) : null;
    }
}
