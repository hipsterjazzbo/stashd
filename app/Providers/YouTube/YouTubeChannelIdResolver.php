<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Providers\ProviderException;
use App\Providers\ProviderHttpClient;

use function Tempest\Support\str;

final readonly class YouTubeChannelIdResolver
{
    public function __construct(
        private ProviderHttpClient $http,
    ) {
    }

    public function resolve(string $providerInputId): ResolvedYouTubeChannel
    {
        if (YouTubeUriResolver::isChannelId($providerInputId)) {
            return new ResolvedYouTubeChannel(id: $providerInputId);
        }

        $page = match (true) {
            str($providerInputId)->startsWith('handle:') => YouTubeUris::handlePage(str($providerInputId)->afterFirst('handle:')->toString()),
            str($providerInputId)->startsWith('c:') => YouTubeUris::customPage(str($providerInputId)->afterFirst('c:')->toString()),
            str($providerInputId)->startsWith('user:') => YouTubeUris::userPage(str($providerInputId)->afterFirst('user:')->toString()),
            default => throw new ProviderException("Unsupported YouTube channel identifier: {$providerInputId}", 'invalid_channel_identifier'),
        };

        $response = $this->http->get($page);

        if (! $response->isSuccessful()) {
            throw new ProviderException(
                "Unable to resolve YouTube channel for {$providerInputId}.",
                'channel_unavailable',
                $response->statusCode,
            );
        }

        $body = $response->body;

        // Priority order matters: the page embeds many unrelated channelId/browseId
        // occurrences (featured channels, recommendations). Only these three signals
        // reliably identify the page's own owning channel, and there is no safe fallback.
        $channelId = $this->extractCanonicalChannelId($body)
            ?? $this->extractOgUrlChannelId($body)
            ?? $this->extractExternalIdChannelId($body);

        if ($channelId === null) {
            throw new ProviderException("Could not resolve channel ID for {$providerInputId}.", 'channel_resolution_failed');
        }

        return new ResolvedYouTubeChannel(
            id: $channelId,
            title: $this->extractTitle($body),
            avatarUri: $this->extractAvatarUri($body),
            estimatedVideoCount: $this->extractEstimatedVideoCount($body),
        );
    }

    private function extractCanonicalChannelId(string $body): ?string
    {
        if (! preg_match('/<link\b[^>]*\brel=["\']canonical["\'][^>]*>/i', $body, $tag)) {
            return null;
        }

        if (! preg_match('/\bhref=["\']https:\/\/www\.youtube\.com\/channel\/(UC[\w-]{22})["\']/i', $tag[0], $href)) {
            return null;
        }

        return $href[1];
    }

    private function extractOgUrlChannelId(string $body): ?string
    {
        if (! preg_match('/<meta\b[^>]*\bproperty=["\']og:url["\'][^>]*>/i', $body, $tag)) {
            return null;
        }

        if (! preg_match('/\bcontent=["\']https:\/\/www\.youtube\.com\/channel\/(UC[\w-]{22})["\']/i', $tag[0], $content)) {
            return null;
        }

        return $content[1];
    }

    private function extractExternalIdChannelId(string $body): ?string
    {
        if (! preg_match('/"externalId"\s*:\s*"(UC[\w-]{22})"/', $body, $match)) {
            return null;
        }

        return $match[1];
    }

    private function extractTitle(string $body): ?string
    {
        $content = $this->extractMetaContent($body, 'og:title');

        return $content !== null && $content !== '' ? $content : null;
    }

    private function extractAvatarUri(string $body): ?string
    {
        $content = $this->extractMetaContent($body, 'og:image');

        return $content !== null && $content !== '' ? $content : null;
    }

    private function extractMetaContent(string $body, string $property): ?string
    {
        if (! preg_match('/<meta\b[^>]*\bproperty=["\']' . preg_quote($property, '/') . '["\'][^>]*>/i', $body, $tag)) {
            return null;
        }

        if (! preg_match('/\bcontent=["\']([^"\']*)["\']/i', $tag[0], $content)) {
            return null;
        }

        return trim($content[1]);
    }

    private function extractEstimatedVideoCount(string $body): ?int
    {
        return $this->extractEstimatedVideoCountFromPageHeader($body)
            ?? $this->extractEstimatedVideoCountFromMicroformat($body);
    }

    /**
     * Current channel pages report the owner's own video count inside the page
     * header's metadata row (alongside the subscriber count), not under a
     * dedicated "videosCountText" key. The bare "pageHeaderRenderer" string also
     * appears earlier in the page as a client preload/message-name hint (an array
     * of renderer type names, not real data), so this anchors on the more specific
     * "header":{"pageHeaderRenderer" prefix that only wraps the actual instance,
     * then stays within a bounded window to avoid unrelated "featured channels"
     * shelf decoys.
     */
    private function extractEstimatedVideoCountFromPageHeader(string $body): ?int
    {
        $headerOffset = strpos($body, '"header":{"pageHeaderRenderer"');

        if ($headerOffset === false) {
            return null;
        }

        $window = substr($body, $headerOffset, 12_000);

        if (! preg_match('/"content"\s*:\s*"(\d[\d.,]*\s*[KkMm]?)\s*videos"/', $window, $match)) {
            return null;
        }

        return $this->parseApproximateCount($match[1]);
    }

    /**
     * Legacy shape kept as a defensive fallback; no longer observed on current
     * YouTube channel pages but cheap to check and safe (the key is specific
     * enough not to collide with anything else).
     */
    private function extractEstimatedVideoCountFromMicroformat(string $body): ?int
    {
        if (! preg_match('/"videosCountText"\s*:\s*\{\s*"runs"\s*:\s*\[\s*\{\s*"text"\s*:\s*"([^"]*)"/', $body, $match)) {
            return null;
        }

        return $this->parseApproximateCount($match[1]);
    }

    private function parseApproximateCount(string $text): ?int
    {
        if (! preg_match('/([\d.,]+)\s*([KkMm]?)/', $text, $match)) {
            return null;
        }

        $number = (float) str_replace(',', '', $match[1]);
        $multiplier = match (strtoupper($match[2])) {
            'K' => 1_000,
            'M' => 1_000_000,
            default => 1,
        };

        return (int) round($number * $multiplier);
    }
}
