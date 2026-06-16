<?php

declare(strict_types=1);

namespace App\Services\Stash;

use App\Domain\Provider\DiscoveredItem;

final class DiscoveredItemSerializer
{
    /** @return array<string, mixed> */
    public static function toArray(DiscoveredItem $item): array
    {
        $payload = [
            'provider_item_id' => $item->providerItemId,
            'canonical_uri' => $item->canonicalUri->toString(),
            'title' => $item->title,
            'duration_seconds' => $item->durationSeconds,
            'published_at' => $item->publishedAt?->toRfc3339(useZ: true),
            'thumbnail_uri' => $item->thumbnailUri?->toString(),
        ];

        if ($item->rawMetadata !== null) {
            $payload['raw_metadata'] = $item->rawMetadata;
        }

        return $payload;
    }

    /**
     * @param list<DiscoveredItem> $items
     *
     * @return list<array<string, mixed>>
     */
    public static function manyToArray(array $items): array
    {
        return array_map(self::toArray(...), $items);
    }
}
