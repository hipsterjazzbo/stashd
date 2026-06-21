<?php

declare(strict_types=1);

namespace App\Broadcasts;

use Tempest\Mapper\SerializeAs;

#[SerializeAs('media_server_scan_trigger_settings')]
final readonly class MediaServerScanTriggerSettings
{
    public function __construct(
        public string $mediaServerConnectionId,
    ) {
    }

    /** @param array<string, mixed>|null $settings */
    public static function fromArray(?array $settings): ?self
    {
        if ($settings === null) {
            return null;
        }

        $connectionId = self::stringValue(
            $settings['mediaServerConnectionId'] ?? $settings['media_server_connection_id'] ?? null,
        );

        return $connectionId === null ? null : new self($connectionId);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'mediaServerConnectionId' => $this->mediaServerConnectionId,
        ];
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
