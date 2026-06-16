<?php

declare(strict_types=1);

namespace App\Broadcasts\Formats;

use App\Broadcasts\BroadcastType;

/** Jellyfin-friendly series layout with SxxExxx naming and minimal NFO sidecars. */
final readonly class JellyfinSeriesBroadcastType extends AbstractSeriesBroadcastType
{
    public function key(): string
    {
        return BroadcastType::JellyfinSeries->value;
    }

    protected function profile(): SeriesFormatOptions
    {
        return new SeriesFormatOptions(
            mediaServerEpisodeNaming: true,
            generateNfo: true,
            attemptPosterHardlink: true,
        );
    }
}
