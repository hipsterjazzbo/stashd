<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Providers\Core\DiscoveredItem;
use App\Providers\ProviderHttpClient;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;

use function Tempest\Support\str;

final readonly class YouTubeVideoDiscovery
{
    public function __construct(
        private ProviderHttpClient $http,
    ) {
    }

    /** @return list<DiscoveredItem> */
    public function discover(ResolvedInput $input): array
    {
        $videoId = $input->providerInputId;
        $canonicalUri = YouTubeUris::watch($videoId);
        $title = $input->title ?? "YouTube Video {$videoId}";
        $thumbnailUri = null;
        $rawMetadata = ['video_id' => $videoId];

        $response = $this->http->get(YouTubeUris::oembed($canonicalUri));

        if ($response->isSuccessful()) {
            try {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
                $title = is_string($payload['title'] ?? null) && str($payload['title'])->trim()->isNotEmpty()
                    ? str($payload['title'])->trim()->toString()
                    : $title;
                $thumbnailUri = is_string($payload['thumbnail_url'] ?? null) && str($payload['thumbnail_url'])->trim()->isNotEmpty()
                    ? StashdUri::parse(str($payload['thumbnail_url'])->trim()->toString())
                    : null;
                $rawMetadata['oembed'] = $payload;
            } catch (\JsonException) {
            }
        }

        return [
            new DiscoveredItem(
                providerItemId: $videoId,
                canonicalUri: $canonicalUri,
                title: $title,
                thumbnailUri: $thumbnailUri,
                rawMetadata: $rawMetadata,
            ),
        ];
    }
}
