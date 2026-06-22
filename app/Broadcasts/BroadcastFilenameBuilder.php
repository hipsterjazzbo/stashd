<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Stashes\StashItemRecord;
use App\System\Storage\PathSanitizer;
use App\Vault\MediaItemRecord;

use function Tempest\Support\str;

final class BroadcastFilenameBuilder
{
    public function seasonFolder(StashItemRecord $stashItem, ?int $seasonOverride = null): string
    {
        $season = $seasonOverride ?? $stashItem->seasonNumber ?? 1;

        return sprintf('Season %02d', max(1, $season));
    }

    public function mediaServerEpisodeFilename(
        StashItemRecord $stashItem,
        MediaItemRecord $mediaItem,
        string $sourcePath,
        int $position,
        ?int $seasonOverride = null,
    ): string {
        $season = max(1, $seasonOverride ?? $stashItem->seasonNumber ?? 1);
        $episode = max(1, $stashItem->episodeNumber ?? $position);
        $title = $stashItem->displayTitle ?? $mediaItem->title;
        $safeTitle = $this->sanitizeTitle($title);
        $extension = $this->extensionFromPath($sourcePath);

        return sprintf('S%02dE%03d - %s.%s', $season, $episode, $safeTitle, $extension);
    }

    public function episodeFilename(
        StashItemRecord $stashItem,
        MediaItemRecord $mediaItem,
        string $sourcePath,
        int $position,
    ): string {
        $title = $stashItem->displayTitle ?? $mediaItem->title;
        $safeTitle = $this->sanitizeTitle($title);
        $extension = $this->extensionFromPath($sourcePath);

        return sprintf('%03d - %s.%s', max(1, $position), $safeTitle, $extension);
    }

    private function sanitizeTitle(string $title): string
    {
        $segment = str($title)->trim()->toString();
        $segment = (string) preg_replace('/[^a-zA-Z0-9._ -]+/', '-', $segment);
        $segment = trim(preg_replace('/\s+/', ' ', $segment) ?? '', '-. ');

        if ($segment === '') {
            return 'untitled';
        }

        if (strlen($segment) > 120) {
            $segment = substr($segment, 0, 120);
            $segment = rtrim($segment, '-. ');
        }

        try {
            return PathSanitizer::sanitizeSegment(str_replace(' ', '-', $segment));
        } catch (\InvalidArgumentException) {
            return 'untitled';
        }
    }

    private function extensionFromPath(string $path): string
    {
        $basename = basename($path);
        $dot = strrpos($basename, '.');

        if ($dot === false || $dot === strlen($basename) - 1) {
            return 'bin';
        }

        $ext = strtolower(substr($basename, $dot + 1));

        try {
            return PathSanitizer::sanitizeSegment($ext);
        } catch (\InvalidArgumentException) {
            return 'bin';
        }
    }
}
