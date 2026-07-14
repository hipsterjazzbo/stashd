<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Timeline\TimelineEntryRepository;
use App\Vault\MediaItemId;

final readonly class PodcastChapterJsonBuilder
{
    public function __construct(private TimelineEntryRepository $entries)
    {
    }

    public function build(MediaItemId $mediaItemId): string
    {
        $chapters = [];

        foreach ($this->entries->listForMediaItem($mediaItemId) as $entry) {
            $chapters[] = [
                'startTime' => $entry->startSeconds,
                'title' => $entry->title ?? $entry->category->value,
            ];
        }

        return json_encode(['version' => '1.2.0', 'chapters' => $chapters], JSON_THROW_ON_ERROR);
    }
}
