<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemId;

/** Captures yt-dlp's provider chapters from the Vault source sidecar. */
final readonly class YtdlpChapterCapture
{
    public function __construct(
        private AssetRepository $assets,
        private TimelineEntryRepository $entries,
    ) {
    }

    public function capture(MediaItemId $mediaItemId): int
    {
        $source = $this->assets->findByMediaItemAndRole($mediaItemId, AssetRole::SourceJson);

        if ($source?->state !== AssetState::Ready || $source->path === null || ! is_readable($source->path)) {
            return 0;
        }

        $payload = json_decode((string) file_get_contents($source->path), true);
        $chapters = is_array($payload) ? ($payload['result']['extract_info']['chapters'] ?? null) : null;

        if (! is_array($chapters)) {
            return 0;
        }

        $captured = 0;

        foreach ($chapters as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            $start = $chapter['start_time'] ?? null;
            $end = $chapter['end_time'] ?? null;

            if (! is_numeric($start) || ! is_numeric($end) || (float) $start < 0 || (float) $end <= (float) $start) {
                continue;
            }

            $title = isset($chapter['title']) && is_string($chapter['title']) ? trim($chapter['title']) : null;
            $externalId = hash('sha256', json_encode([(float) $start, (float) $end, $title], JSON_THROW_ON_ERROR));
            $entry = $this->entries->findBySourceAndExternalId($mediaItemId, TimelineEntrySource::Ytdlp, $externalId);

            if ($entry === null) {
                $this->entries->create(
                    mediaItemId: $mediaItemId,
                    source: TimelineEntrySource::Ytdlp,
                    kind: TimelineEntryKind::Chapter,
                    category: TimelineEntryCategory::Chapter,
                    startSeconds: (float) $start,
                    endSeconds: (float) $end,
                    title: $title === '' ? null : $title,
                    externalId: $externalId,
                    raw: $chapter,
                );
                $captured++;
            }
        }

        return $captured;
    }
}
