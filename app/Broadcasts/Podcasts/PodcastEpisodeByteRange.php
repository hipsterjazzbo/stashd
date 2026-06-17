<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

/**
 * Parses a single-range `Range: bytes=...` request header against a known
 * total length. Stashd only supports single-range requests: a header naming
 * more than one range, or one that is syntactically invalid per RFC 7233
 * (e.g. a reversed `start-end`), is treated the same as no header at all so
 * the caller falls back to serving the full file. Real podcast clients only
 * ever request one range at a time for seeking.
 */
final readonly class PodcastEpisodeByteRange
{
    private function __construct(
        public bool $present,
        public bool $satisfiable,
        public int $start,
        public int $end,
        public int $totalLength,
    ) {
    }

    public static function fromHeader(?string $rangeHeader, int $totalLength): self
    {
        $absent = new self(present: false, satisfiable: false, start: 0, end: 0, totalLength: $totalLength);

        if ($rangeHeader === null || ! str_starts_with($rangeHeader, 'bytes=')) {
            return $absent;
        }

        $spec = substr($rangeHeader, strlen('bytes='));

        if (str_contains($spec, ',')) {
            return $absent;
        }

        if (preg_match('/^(\d+)-(\d+)$/', $spec, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];

            if ($start > $end) {
                return $absent;
            }

            return self::resolved($start, min($end, $totalLength - 1), $totalLength);
        }

        if (preg_match('/^(\d+)-$/', $spec, $matches) === 1) {
            return self::resolved((int) $matches[1], $totalLength - 1, $totalLength);
        }

        if (preg_match('/^-(\d+)$/', $spec, $matches) === 1) {
            $suffixLength = (int) $matches[1];

            if ($suffixLength <= 0) {
                return $absent;
            }

            return self::resolved(max(0, $totalLength - $suffixLength), $totalLength - 1, $totalLength);
        }

        return $absent;
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    private static function resolved(int $start, int $end, int $totalLength): self
    {
        return new self(
            present: true,
            satisfiable: $start < $totalLength,
            start: $start,
            end: $end,
            totalLength: $totalLength,
        );
    }
}
