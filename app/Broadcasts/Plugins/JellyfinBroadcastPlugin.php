<?php

declare(strict_types=1);

namespace App\Broadcasts\Plugins;

use App\Broadcasts\StashdBroadcast;

/**
 * Jellyfin-friendly series layout with SxxExxx naming and NFO sidecars.
 */
#[StashdBroadcast('Jellyfin Series', 'Jellyfin-compatible series layout with SxxExxx naming, NFO sidecars, and poster hardlinks.')]
final class JellyfinBroadcastPlugin extends AbstractSeriesBroadcastPlugin
{
    protected function broadcastKey(): string
    {
        return 'jellyfin';
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
