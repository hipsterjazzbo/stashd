<?php

declare(strict_types=1);

namespace App\Domain\MediaServer;

final readonly class MediaServerStatus
{
    public function __construct(
        public bool $ok,
        public string $message,
        public ?string $serverName = null,
        public ?string $version = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
            'server_name' => $this->serverName,
            'version' => $this->version,
        ];
    }
}
