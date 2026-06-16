<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Types;

use App\Domain\Broadcast\BroadcastType;

/** Plex-friendly series layout with SxxExxx naming and minimal NFO sidecars. */
final readonly class PlexSeriesBroadcastType extends AbstractSeriesBroadcastType
{
    public function key(): string
    {
        return BroadcastType::PlexSeries->value;
    }

    protected function profile(): SeriesBroadcastProfile
    {
        return new SeriesBroadcastProfile(
            mediaServerEpisodeNaming: true,
            generateNfo: true,
            attemptPosterHardlink: true,
        );
    }
}
