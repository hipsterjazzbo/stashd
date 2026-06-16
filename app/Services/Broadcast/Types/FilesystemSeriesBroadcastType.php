<?php

declare(strict_types=1);

namespace App\Services\Broadcast\Types;

use App\Domain\Broadcast\BroadcastType;

/** Phase 5A simple filesystem broadcast — no NFO sidecars. */
final readonly class FilesystemSeriesBroadcastType extends AbstractSeriesBroadcastType
{
    public function key(): string
    {
        return BroadcastType::FilesystemSeries->value;
    }

    protected function profile(): SeriesBroadcastProfile
    {
        return new SeriesBroadcastProfile();
    }
}
