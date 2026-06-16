<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Broadcasts\BroadcastItemRecord;

final readonly class PodcastGuid
{
    public function forItem(BroadcastItemRecord $item): string
    {
        return sprintf('stashd:broadcast:%s:item:%s', $item->broadcastId, (string) $item->id);
    }
}
