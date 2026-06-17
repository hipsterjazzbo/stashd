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

    public function resolve(string $providerInputId): string
    {
        if (YouTubeUriResolver::isChannelId($providerInputId)) {
            return $providerInputId;
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

        $channelId = str($response->body)->match('/"channelId"\s*:\s*"(UC[\w-]{22})"/', 1)
            ?? str($response->body)->match('/"browseId"\s*:\s*"(UC[\w-]{22})"/', 1);

        if (! is_string($channelId) || $channelId === '') {
            throw new ProviderException("Could not resolve channel ID for {$providerInputId}.", 'channel_resolution_failed');
        }

        return $channelId;
    }
}
