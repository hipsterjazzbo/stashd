<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Broadcasts\SponsorBlockSegment;
use App\Vault\MediaItemId;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final readonly class SponsorBlockTimelineSynchronizer
{
    public function __construct(private TimelineEntryRepository $entries)
    {
    }

    /** @param list<SponsorBlockSegment> $segments */
    public function sync(MediaItemId $mediaItemId, array $segments): bool
    {
        $existing = [];

        foreach ($this->entries->listForMediaItem($mediaItemId) as $entry) {
            if ($entry->source === TimelineEntrySource::SponsorBlock && $entry->externalId !== null) {
                $existing[$entry->externalId] = $entry;
            }
        }

        $changed = false;
        $checkedAt = DateTime::now(Timezone::UTC);

        foreach ($segments as $segment) {
            $entry = $existing[$segment->externalId] ?? null;
            unset($existing[$segment->externalId]);

            if ($entry === null) {
                $this->entries->create($mediaItemId, TimelineEntrySource::SponsorBlock, TimelineEntryKind::Segment, $segment->category, $segment->startSeconds, $segment->endSeconds, $segment->title, $segment->externalId, $segment->raw);
                $changed = true;

                continue;
            }

            $changed = $changed || $entry->category !== $segment->category
                || $entry->startSeconds !== $segment->startSeconds
                || $entry->endSeconds !== $segment->endSeconds
                || $entry->title !== $segment->title;
            $entry->category = $segment->category;
            $entry->startSeconds = $segment->startSeconds;
            $entry->endSeconds = $segment->endSeconds;
            $entry->title = $segment->title;
            $entry->raw = $segment->raw;
            $entry->lastCheckedAt = $checkedAt;
            $this->entries->save($entry);
        }

        foreach ($existing as $entry) {
            $entry->delete();
            $changed = true;
        }

        return $changed;
    }
}
