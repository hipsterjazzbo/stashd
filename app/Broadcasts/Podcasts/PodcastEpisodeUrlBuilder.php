<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Config\StashdConfig;

final readonly class PodcastEpisodeUrlBuilder
{
    public function __construct(
        private StashdConfig $config,
    ) {
    }

    public function feedUrl(string $broadcastToken): string
    {
        return $this->baseUrl() . '/b/' . rawurlencode($broadcastToken) . '/feed.xml';
    }

    public function episodeUrl(string $broadcastToken, string $itemToken, string $extension): string
    {
        return $this->baseUrl()
            . '/b/' . rawurlencode($broadcastToken)
            . '/items/' . rawurlencode($itemToken)
            . '/episode.' . rawurlencode($extension);
    }

    public function artworkUrl(string $broadcastToken, string $itemToken): string
    {
        return $this->baseUrl() . '/b/' . rawurlencode($broadcastToken) . '/items/' . rawurlencode($itemToken) . '/artwork';
    }

    public function chapterUrl(string $broadcastToken, string $itemToken): string
    {
        return $this->baseUrl() . '/b/' . rawurlencode($broadcastToken) . '/items/' . rawurlencode($itemToken) . '/chapters.json';
    }

    public function transcriptUrl(string $broadcastToken, string $itemToken): string
    {
        return $this->baseUrl() . '/b/' . rawurlencode($broadcastToken) . '/items/' . rawurlencode($itemToken) . '/transcript';
    }

    private function baseUrl(): string
    {
        return rtrim($this->config->publicUrl, '/');
    }
}
