<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Broadcasts\Formats\BroadcastFormat;
use App\Broadcasts\Formats\FilesystemSeriesBroadcastType;
use App\Broadcasts\Formats\JellyfinSeriesBroadcastType;
use App\Broadcasts\Formats\PlexSeriesBroadcastType;

final readonly class BroadcastTypeRegistry
{
    /** @var array<string, BroadcastFormat> */
    private array $handlers;

    public function __construct(
        FilesystemSeriesBroadcastType $filesystemSeries,
        JellyfinSeriesBroadcastType $jellyfinSeries,
        PlexSeriesBroadcastType $plexSeries,
    ) {
        $this->handlers = [
            $filesystemSeries->key() => $filesystemSeries,
            $jellyfinSeries->key() => $jellyfinSeries,
            $plexSeries->key() => $plexSeries,
        ];
    }

    public function handlerFor(BroadcastType $type): BroadcastFormat
    {
        $handler = $this->handlers[$type->value] ?? null;

        if ($handler === null) {
            throw BroadcastException::withCode(
                'broadcast_type_unsupported',
                'Broadcast type is not implemented yet: ' . $type->value,
            );
        }

        return $handler;
    }
}
