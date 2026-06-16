<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Types;

/** Per-type filesystem layout and sidecar policy. */
final readonly class SeriesBroadcastProfile
{
    public function __construct(
        public bool $mediaServerEpisodeNaming = false,
        public bool $generateNfo = false,
        public bool $attemptPosterHardlink = false,
    ) {
    }
}
