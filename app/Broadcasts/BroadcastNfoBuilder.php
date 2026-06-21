<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Stashes\StashItemRecord;
use App\Vault\MediaItemRecord;
use Tempest\DateTime\FormatPattern;
use Tempest\DateTime\Timezone;

/** Minimal deterministic NFO sidecars from stored metadata only. */
final class BroadcastNfoBuilder
{
    public function tvShowNfo(string $seriesTitle, ?string $overview = null): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<tvshow>',
            '  <title>' . $this->escape($seriesTitle) . '</title>',
        ];

        if ($overview !== null && trim($overview) !== '') {
            $lines[] = '  <plot>' . $this->escape(trim($overview)) . '</plot>';
        }

        $lines[] = '</tvshow>';

        return implode("\n", $lines) . "\n";
    }

    public function episodeNfo(
        StashItemRecord $stashItem,
        MediaItemRecord $mediaItem,
        int $season,
        int $episode,
    ): string {
        $title = $stashItem->displayTitle ?? $mediaItem->title;
        $overview = $stashItem->displayDescription ?? $mediaItem->description;

        $lines = [
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<episodedetails>',
            '  <title>' . $this->escape($title) . '</title>',
            '  <season>' . $season . '</season>',
            '  <episode>' . $episode . '</episode>',
        ];

        if ($overview !== null && trim($overview) !== '') {
            $lines[] = '  <plot>' . $this->escape(trim($overview)) . '</plot>';
        }

        if ($mediaItem->publishedAt !== null) {
            $lines[] = '  <aired>' . $this->escape($mediaItem->publishedAt->format(FormatPattern::SQL_DATE, Timezone::UTC)) . '</aired>';
        }

        $lines[] = '</episodedetails>';

        return implode("\n", $lines) . "\n";
    }

    public function episodeNfoFilename(string $episodeMediaFilename): string
    {
        $dot = strrpos($episodeMediaFilename, '.');

        if ($dot === false) {
            return $episodeMediaFilename . '.nfo';
        }

        return substr($episodeMediaFilename, 0, $dot) . '.nfo';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
