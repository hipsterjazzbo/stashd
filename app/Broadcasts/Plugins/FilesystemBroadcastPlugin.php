<?php

declare(strict_types=1);

namespace App\Broadcasts\Plugins;

use App\Broadcasts\Formats\SeriesFormatOptions;
use App\Broadcasts\StashdBroadcast;

/**
 * Simple filesystem broadcast — no NFO sidecars, standard episode naming.
 */
#[StashdBroadcast('Filesystem', 'Simple filesystem broadcast with standard episode naming and no sidecars.')]
final class FilesystemBroadcastPlugin extends AbstractSeriesBroadcastPlugin
{
    protected function broadcastKey(): string
    {
        return 'filesystem';
    }

    protected function profile(): SeriesFormatOptions
    {
        return new SeriesFormatOptions();
    }
}
