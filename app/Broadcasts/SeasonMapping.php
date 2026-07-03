<?php

declare(strict_types=1);

namespace App\Broadcasts;

/**
 * Optional `stash_input_id -> season` assignment for series-style broadcasts,
 * stored under the `season_mapping` key of `BroadcastRecord.settings`.
 *
 * Stash input ids are opaque identifiers, not DTO field names, so they must
 * stay out of the snake/camel API boundary transform — see
 * `BroadcastController::updateSeasonMapping()` and `BroadcastResource`.
 */
final readonly class SeasonMapping
{
    /** @param array<string, int> $seasonsByStashInputId */
    private function __construct(
        private array $seasonsByStashInputId,
    ) {
    }

    /** @param array<string, mixed> $settings decoded BroadcastRecord.settings */
    public static function fromBroadcastSettings(array $settings): self
    {
        $raw = $settings['season_mapping'] ?? null;

        if (! is_array($raw)) {
            return new self([]);
        }

        $map = [];

        foreach ($raw as $stashInputId => $season) {
            if (is_string($stashInputId) && $stashInputId !== '' && is_int($season) && $season >= 1) {
                $map[$stashInputId] = $season;
            }
        }

        return new self($map);
    }

    public function seasonFor(?string $stashInputId): ?int
    {
        if ($stashInputId === null) {
            return null;
        }

        return $this->seasonsByStashInputId[$stashInputId] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->seasonsByStashInputId === [];
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return $this->seasonsByStashInputId;
    }
}
