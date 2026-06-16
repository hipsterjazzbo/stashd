<?php

declare(strict_types=1);

namespace App\Domain\MediaServer;

final readonly class MediaServerTriggerResult
{
    public function __construct(
        public bool $ok,
        public string $message,
        public ?int $httpStatus = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
            'http_status' => $this->httpStatus,
        ];
    }
}
