<?php

declare(strict_types=1);

namespace App\MediaServers;

use Tempest\Mapper\SerializeAs;

#[SerializeAs('media_server_library_selection')]
final readonly class MediaServerLibrarySelection
{
    public function __construct(
        public ?string $libraryId = null,
        public ?string $libraryName = null,
        public ?string $libraryType = null,
    ) {
    }

    /** @param array<string, mixed>|null $settings */
    public static function fromArray(?array $settings): ?self
    {
        if ($settings === null) {
            return null;
        }

        $selection = new self(
            libraryId: self::stringValue($settings['libraryId'] ?? $settings['library_id'] ?? null),
            libraryName: self::stringValue($settings['libraryName'] ?? $settings['library_name'] ?? null),
            libraryType: self::stringValue($settings['libraryType'] ?? $settings['library_type'] ?? null),
        );

        if ($selection->libraryId === null && $selection->libraryName === null && $selection->libraryType === null) {
            return null;
        }

        return $selection;
    }

    public function toLibraryRef(): ?MediaServerLibraryRef
    {
        if ($this->libraryId === null) {
            return null;
        }

        return new MediaServerLibraryRef(
            id: $this->libraryId,
            name: $this->libraryName ?? 'Library',
            type: $this->libraryType,
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'libraryId' => $this->libraryId,
            'libraryName' => $this->libraryName,
            'libraryType' => $this->libraryType,
        ], static fn (?string $value): bool => $value !== null);
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
