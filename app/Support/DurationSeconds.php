<?php

declare(strict_types=1);

namespace App\Support;

use Tempest\DateTime\Duration;

/** Converts between plain int-seconds (DTOs, API output) and Duration (record properties). */
final class DurationSeconds
{
    public static function toDuration(?int $seconds): ?Duration
    {
        return $seconds === null ? null : Duration::seconds($seconds);
    }

    public static function toSeconds(?Duration $duration): ?int
    {
        return $duration === null ? null : (int) $duration->getTotalSeconds();
    }
}
