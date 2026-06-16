<?php

declare(strict_types=1);

namespace App\Broadcasts\Formats;

use App\Broadcasts\BroadcastType;

/** Phase 5A simple filesystem broadcast — no NFO sidecars. */
final readonly class FilesystemSeriesBroadcastType extends AbstractSeriesBroadcastType
{
    public function key(): string
    {
        return BroadcastType::FilesystemSeries->value;
    }

    protected function profile(): SeriesFormatOptions
    {
        return new SeriesFormatOptions();
    }
}
