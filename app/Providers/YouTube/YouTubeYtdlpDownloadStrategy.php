<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Config\YtdlpConfig;
use App\Downloads\Ytdlp\YtdlpGateway;
use App\Providers\StashdUri;

/**
 * ytdlphp-backed download adapter for the YouTube provider strategy registry.
 *
 * All yt-dlp process execution stays inside {@see YtdlpGateway} / ytdlphp.
 */
final readonly class YouTubeYtdlpDownloadStrategy implements YtdlpDownloadAdapter
{
    public const string STRATEGY_KEY = 'youtube.ytdlp';

    public function __construct(
        private YtdlpConfig $config,
        private YtdlpGateway $gateway,
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

    public function implementationName(): string
    {
        return 'ytdlphp';
    }

    public function implementationVersion(): ?string
    {
        return $this->gateway->probe()->version;
    }

    public function probe(StashdUri $canonicalUri): array
    {
        $probe = $this->gateway->probe();

        return [
            'available' => $this->config->realDownloadsEnabled() && $probe->available,
            'implementation' => $this->implementationName(),
            'implementation_version' => $probe->version,
            'ytdlp_binary' => $probe->binary,
            'canonical_uri' => $canonicalUri->toString(),
            'message' => $this->config->realDownloadsEnabled()
                ? $probe->message
                : 'Real downloads are disabled (STASHD_REAL_DOWNLOADS_ENABLED=0).',
        ];
    }
}
