<?php

declare(strict_types=1);

namespace App\System\RoadRunner;

use Generator;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Adapts a generator that yields raw byte chunks (e.g. a file read in bounded
 * slices) to a PSR-7 StreamInterface, so PSR7Worker::respond() with
 * chunkSize > 0 streams those chunks to the client one at a time instead of
 * draining the whole generator into a single string first.
 *
 * This is the raw-bytes sibling of {@see GeneratorEventStream}: same
 * forward-only, read-once, size-unknown semantics (so RoadRunner's
 * streamToGenerator() never takes its "small enough, send as one string"
 * shortcut), but it forwards each yielded chunk verbatim rather than framing
 * it as a Server-Sent Event. It exists so large podcast episodes (tens to
 * hundreds of MB) can be served without ever holding the whole file -- or a
 * whole requested range -- in memory, which would blow the worker's
 * max_worker_memory cap (see PodcastEpisodeController, .rr.yaml).
 */
final class GeneratorFileStream implements StreamInterface
{
    private bool $closed = false;

    private string $buffer = '';

    private int $bytesRead = 0;

    public function __construct(
        private Generator $chunks,
    ) {
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function detach(): null
    {
        $this->closed = true;

        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        return $this->bytesRead;
    }

    public function eof(): bool
    {
        return $this->buffer === '' && ! $this->chunks->valid();
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('GeneratorFileStream is not seekable.');
    }

    public function rewind(): void
    {
        // PSR7Worker::streamToGenerator() calls rewind() before its first read();
        // there is nothing to rewind to until reading starts.
        if ($this->bytesRead > 0) {
            throw new RuntimeException('GeneratorFileStream cannot be rewound after reading has started.');
        }
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('GeneratorFileStream is not writable.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        if ($this->closed) {
            return '';
        }

        if ($this->buffer === '' && $this->chunks->valid()) {
            $this->buffer = (string) $this->chunks->current();
            $this->chunks->next();
        }

        $chunk = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, strlen($chunk));
        $this->bytesRead += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        $contents = $this->buffer;
        $this->buffer = '';

        while ($this->chunks->valid()) {
            $contents .= (string) $this->chunks->current();
            $this->chunks->next();
        }

        $this->bytesRead += strlen($contents);

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
