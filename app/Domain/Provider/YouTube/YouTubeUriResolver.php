<?php

declare(strict_types=1);

namespace App\Domain\Provider\YouTube;

use App\Domain\Provider\ProviderException;
use App\Domain\Provider\ResolvedInput;
use App\Domain\Provider\StashdUri;

use function Tempest\Support\str;

final class YouTubeUriResolver
{
    public static function resolve(StashdUri $uri): ResolvedInput
    {
        if (! YouTubeUriDetector::isYouTube($uri)) {
            throw new \InvalidArgumentException("Not a YouTube URL: {$uri}");
        }

        if ($uri->host() === 'youtu.be') {
            $videoId = str($uri->path())->trim('/')->toString();

            return self::videoInput($uri, $videoId);
        }

        if ($uri->pathStartsWith('/@')) {
            $handle = str($uri->path())->afterFirst('/@')->before('/')->trim('/')->toString();

            if ($handle === '') {
                throw ProviderException::withUnsupportedUrl($uri->toString(), 'YouTube handle URL is missing a handle.');
            }

            return self::channelInput($uri, 'handle:' . $handle, "YouTube @{$handle}");
        }

        if ($uri->pathStartsWith('/channel/')) {
            $channelId = str($uri->path())->afterFirst('/channel/')->before('/')->trim('/')->toString();

            if (! self::isChannelId($channelId)) {
                throw ProviderException::withUnsupportedUrl($uri->toString(), 'YouTube channel URL is missing a valid channel ID.');
            }

            return self::channelInput($uri, $channelId, "YouTube Channel {$channelId}");
        }

        if ($uri->path() === '/watch' || $uri->pathStartsWith('/watch/')) {
            $videoId = str((string) ($uri->queryParam('v') ?? ''))->trim()->toString();

            if ($videoId !== '') {
                return self::videoInput($uri, $videoId);
            }

            throw ProviderException::withUnsupportedUrl($uri->toString(), 'YouTube watch URL is missing a video ID.');
        }

        if ($uri->path() === '/playlist' || $uri->pathStartsWith('/playlist/')) {
            $playlistId = str((string) ($uri->queryParam('list') ?? ''))->trim()->toString();

            if ($playlistId === '') {
                throw ProviderException::withUnsupportedUrl($uri->toString(), 'YouTube playlist URL is missing a list ID.');
            }

            return self::playlistInput($uri, $playlistId);
        }

        throw ProviderException::withUnsupportedUrl($uri->toString(), 'Unsupported YouTube URL pattern.');
    }

    private static function channelInput(StashdUri $uri, string $providerInputId, ?string $title): ResolvedInput
    {
        return new ResolvedInput(
            providerKey: 'youtube',
            inputType: YouTubeInputType::Channel->value,
            sourceUri: $uri,
            providerInputId: $providerInputId,
            title: $title,
        );
    }

    private static function playlistInput(StashdUri $uri, string $playlistId): ResolvedInput
    {
        return new ResolvedInput(
            providerKey: 'youtube',
            inputType: YouTubeInputType::Playlist->value,
            sourceUri: $uri,
            providerInputId: $playlistId,
            title: "YouTube Playlist {$playlistId}",
        );
    }

    private static function videoInput(StashdUri $uri, string $videoId): ResolvedInput
    {
        if (! self::isVideoId($videoId)) {
            throw ProviderException::withUnsupportedUrl($uri->toString(), 'YouTube video URL is missing a valid video ID.');
        }

        return new ResolvedInput(
            providerKey: 'youtube',
            inputType: YouTubeInputType::Video->value,
            sourceUri: $uri,
            providerInputId: $videoId,
            title: "YouTube Video {$videoId}",
        );
    }

    public static function isChannelId(string $value): bool
    {
        return str($value)->matches('/^UC[\w-]{22}$/');
    }

    public static function isVideoId(string $value): bool
    {
        return str($value)->matches('/^[\w-]{11}$/');
    }
}
