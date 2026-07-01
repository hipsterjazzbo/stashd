<?php

declare(strict_types=1);

namespace App\Broadcasts\Plugins;

/** Per-type filesystem layout and sidecar policy. */
final readonly class SeriesFormatOptions
{
    public function __construct(
        public bool $mediaServerEpisodeNaming = false,
        public bool $generateNfo = false,
        public bool $attemptPosterHardlink = false,
    ) {
    }
}
