<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Domain\Broadcast\BroadcastException;
use App\Domain\Broadcast\BroadcastType;
use App\Domain\Broadcast\Contract\BroadcastTypeHandler;
use App\Services\Broadcast\Types\FilesystemSeriesBroadcastType;
use App\Services\Broadcast\Types\JellyfinSeriesBroadcastType;
use App\Services\Broadcast\Types\PlexSeriesBroadcastType;

final readonly class BroadcastTypeRegistry
{
    /** @var array<string, BroadcastTypeHandler> */
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

    public function handlerFor(BroadcastType $type): BroadcastTypeHandler
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
