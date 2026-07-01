<?php

declare(strict_types=1);

namespace App\Broadcasts\Plugins;

use App\Broadcasts\Plugins\SeriesFormatOptions;
use App\Broadcasts\StashdBroadcast;

/**
 * Plex-friendly series layout with SxxExxx naming and NFO sidecars.
 */
#[StashdBroadcast('Plex Series', 'Plex-compatible series layout with SxxExxx naming, NFO sidecars, and poster hardlinks.')]
final class PlexBroadcastPlugin extends AbstractSeriesBroadcastPlugin
{
    protected function broadcastKey(): string
    {
        return 'plex';
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
