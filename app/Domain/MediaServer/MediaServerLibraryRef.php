<?php

declare(strict_types=1);

namespace App\Domain\MediaServer;

final readonly class MediaServerLibraryRef
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $type = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
        ];
    }
}
