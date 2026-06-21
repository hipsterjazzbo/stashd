<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use Tempest\DateTime\Duration;

/** Parses YouTube Data API ISO 8601 durations (e.g. "PT4M13S") into Tempest's Duration. */
final class YouTubeDurations
{
    public static function parseIso8601(string $duration): ?Duration
    {
        if (! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $matches)) {
            return null;
        }

        return Duration::fromParts(
            hours: (int) ($matches[1] ?? 0),
            minutes: (int) ($matches[2] ?? 0),
            seconds: (int) ($matches[3] ?? 0),
        );
    }
}
