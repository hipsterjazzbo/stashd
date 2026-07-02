<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Providers\StashdUri;

use function Tempest\Support\str;

use Tempest\Support\Uri\Uri;

/** YouTube URI builders — application code should not concatenate YouTube URLs by hand. */
final class YouTubeUris
{
    private const string ORIGIN = 'https://www.youtube.com';
    private const string DATA_API_ORIGIN = 'https://www.googleapis.com/youtube/v3';

    public static function watch(string $videoId): StashdUri
    {
        return self::stashd(
            Uri::from(self::ORIGIN)->withPath('/watch')->withQuery(v: $videoId),
        );
    }

    public static function channelFeed(string $channelId): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/feeds/videos.xml')->withQuery(channel_id: $channelId);
    }

    public static function playlistFeed(string $playlistId): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/feeds/videos.xml')->withQuery(playlist_id: $playlistId);
    }

    public static function channelVideosPage(string $channelId): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/channel/' . $channelId . '/videos');
    }

    public static function playlistPage(string $playlistId): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/playlist')->withQuery(list: $playlistId);
    }

    public static function handlePage(string $handle): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/@' . str($handle)->ltrim('@')->toString());
    }

    public static function customPage(string $name): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/c/' . str($name)->ltrim('/')->toString());
    }

    public static function userPage(string $name): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/user/' . str($name)->ltrim('/')->toString());
    }

    public static function oembed(StashdUri $watchUri): Uri
    {
        return Uri::from(self::ORIGIN)->withPath('/oembed')->withQuery(
            format: 'json',
            url: $watchUri->toString(),
        );
    }

    public static function dataApiVideo(string $videoId, string $apiKey): Uri
    {
        return Uri::from(self::DATA_API_ORIGIN . '/videos')->withQuery(
            id: $videoId,
            part: 'snippet,contentDetails',
            key: $apiKey,
        );
    }

    public static function dataApiChannelContentDetails(string $channelId, string $apiKey): Uri
    {
        return Uri::from(self::DATA_API_ORIGIN . '/channels')->withQuery(
            id: $channelId,
            part: 'contentDetails',
            key: $apiKey,
        );
    }

    public static function dataApiPlaylistItems(string $playlistId, string $apiKey, ?string $pageToken = null): Uri
    {
        $uri = Uri::from(self::DATA_API_ORIGIN . '/playlistItems')->withQuery(
            playlistId: $playlistId,
            part: 'snippet',
            maxResults: 50,
            key: $apiKey,
        );

        return $pageToken !== null ? $uri->addQuery(pageToken: $pageToken) : $uri;
    }

    /** @param list<string> $videoIds */
    public static function dataApiVideosBatch(array $videoIds, string $apiKey): Uri
    {
        return Uri::from(self::DATA_API_ORIGIN . '/videos')->withQuery(
            id: implode(',', $videoIds),
            part: 'snippet,contentDetails,liveStreamingDetails',
            key: $apiKey,
        );
    }

    private static function stashd(Uri $uri): StashdUri
    {
        return new StashdUri($uri);
    }
}
