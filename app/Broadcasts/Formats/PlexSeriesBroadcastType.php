<?php

declare(strict_types=1);

namespace App\Broadcasts\Formats;

use App\Broadcasts\BroadcastType;

/** Plex-friendly series layout with SxxExxx naming and minimal NFO sidecars. */
final readonly class PlexSeriesBroadcastType extends AbstractSeriesBroadcastType
{
    public function key(): string
    {
        return BroadcastType::PlexSeries->value;
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
