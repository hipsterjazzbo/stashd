<?php

declare(strict_types=1);

namespace App\Domain\Provider\YouTube;

use App\Domain\Provider\StashdUri;

final class YouTubeUriDetector
{
    /** @var list<string> */
    private const array HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtu.be',
    ];

    public static function isYouTube(StashdUri $uri): bool
    {
        return in_array($uri->host(), self::HOSTS, true);
    }

    public static function isYouTubeUrl(string $url): bool
    {
        try {
            return self::isYouTube(StashdUri::parse($url));
        } catch (\Throwable) {
            return false;
        }
    }
}
