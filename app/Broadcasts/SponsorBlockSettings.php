<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Timeline\TimelineEntryCategory;
use InvalidArgumentException;

final readonly class SponsorBlockSettings
{
    /** @param list<TimelineEntryCategory> $categories */
    private function __construct(
        public bool $enabled,
        public array $categories,
    ) {
    }

    /** @param array<string, mixed> $settings */
    public static function fromBroadcastSettings(array $settings): self
    {
        $enabled = $settings['sponsorblock_enabled'] ?? false;
        $rawCategories = $settings['sponsorblock_categories'] ?? [];

        if (! is_bool($enabled) || ! is_array($rawCategories)) {
            throw new InvalidArgumentException('SponsorBlock settings are invalid.');
        }

        $categories = [];

        foreach ($rawCategories as $category) {
            if (! is_string($category) || ($parsed = TimelineEntryCategory::tryFrom($category)) === null || $parsed === TimelineEntryCategory::Other) {
                throw new InvalidArgumentException('SponsorBlock categories are invalid.');
            }

            $categories[$parsed->value] = $parsed;
        }

        if ($enabled && $categories === []) {
            throw new InvalidArgumentException('SponsorBlock requires at least one category when enabled.');
        }

        return new self($enabled, array_values($categories));
    }
}
