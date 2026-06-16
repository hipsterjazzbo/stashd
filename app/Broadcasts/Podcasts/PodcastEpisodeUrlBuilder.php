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

    private function baseUrl(): string
    {
        return rtrim($this->config->publicUrl, '/');
    }
}
