<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Types;

use App\Domain\Broadcast\BroadcastType;

/** Jellyfin-friendly series layout with SxxExxx naming and minimal NFO sidecars. */
final readonly class JellyfinSeriesBroadcastType extends AbstractSeriesBroadcastType
{
    public function key(): string
    {
        return BroadcastType::JellyfinSeries->value;
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
