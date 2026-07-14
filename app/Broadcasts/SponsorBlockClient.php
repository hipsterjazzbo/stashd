<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Providers\ProviderHttpClient;
use App\Timeline\TimelineEntryCategory;
use JsonException;
use RuntimeException;

final readonly class SponsorBlockClient
{
    private const array CATEGORIES = [
        'sponsor', 'selfpromo', 'interaction', 'intro', 'outro', 'preview',
        'music_offtopic', 'filler', 'hook', 'poi_highlight', 'chapter',
    ];

    public function __construct(private ProviderHttpClient $http)
    {
    }

    /** @return list<SponsorBlockSegment> */
    public function fetch(string $videoId): array
    {
        $query = http_build_query([
            'videoID' => $videoId,
            'categories' => json_encode(self::CATEGORIES, JSON_THROW_ON_ERROR),
        ], encoding_type: PHP_QUERY_RFC3986);
        $response = $this->http->get('https://sponsor.ajay.app/api/skipSegments?' . $query);

        if ($response->statusCode === 404) {
            return [];
        }

        if (! $response->isSuccessful()) {
            throw new RuntimeException("SponsorBlock returned HTTP {$response->statusCode}.");
        }

        try {
            $segments = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('SponsorBlock returned invalid JSON.', previous: $exception);
        }

        if (! is_array($segments)) {
            throw new RuntimeException('SponsorBlock returned an invalid segment list.');
        }

        return array_values(array_filter(array_map($this->segment(...), $segments)));
    }

    /** @param mixed $raw */
    private function segment(mixed $raw): ?SponsorBlockSegment
    {
        if (! is_array($raw) || ! is_array($raw['segment'] ?? null) || count($raw['segment']) < 2) {
            return null;
        }

        [$start, $end] = $raw['segment'];
        $id = $raw['UUID'] ?? null;

        if (! is_numeric($start) || ! is_numeric($end) || ! is_string($id) || $id === '' || (float) $end <= (float) $start) {
            return null;
        }

        $category = $raw['category'] ?? null;

        return new SponsorBlockSegment(
            externalId: $id,
            category: is_string($category) ? TimelineEntryCategory::tryFrom($category) ?? TimelineEntryCategory::Other : TimelineEntryCategory::Other,
            startSeconds: (float) $start,
            endSeconds: (float) $end,
            title: is_string($raw['description'] ?? null) && $raw['description'] !== '' ? $raw['description'] : null,
            raw: $raw,
        );
    }
}
