<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Config\YouTubeConfig;
use App\System\Secret\SecretsService;

use function Tempest\Support\str;

final readonly class SecretsBackedYouTubeDataApiKeyResolver implements YouTubeDataApiKeyResolver
{
    public const string SECRET_KEY = 'youtube_data_api_key';

    public function __construct(
        private SecretsService $secrets,
        private YouTubeConfig $envConfig,
    ) {
    }

    public function key(): ?string
    {
        $secret = $this->secrets->get(self::SECRET_KEY);

        if ($secret !== null && str($secret)->trim()->isNotEmpty()) {
            return str($secret)->trim()->toString();
        }

        return $this->envConfig->dataApiKey;
    }

    public function hasKey(): bool
    {
        return $this->key() !== null;
    }
}
