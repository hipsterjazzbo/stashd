<?php

declare(strict_types=1);

namespace App\System\RoadRunner;

use Generator;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Tempest\DateTime\Duration;
use Tempest\Http\ServerSentEvent;
use Tempest\Http\ServerSentMessage;

/**
 * Adapts EventStream's generator (app/System/Event/EventsController.php yields
 * ServerSentMessage/ServerSentEvent from a sleep-and-poll loop) to a PSR-7
 * StreamInterface, so PSR7Worker::respond() with chunkSize > 0 can flush each
 * message to the client as it's produced instead of draining the whole
 * generator into one string first (see TempestPsr7Bridge::run()).
 *
 * Forward-only and read-once, like the generator it wraps: `getSize()` is
 * always null so RoadRunner's streamToGenerator() never takes its
 * "already small enough, send as one string" shortcut, and read() advances
 * the generator by exactly one message per call (never waits to fill the
 * caller's requested length) so a single notification is flushed on its own,
 * not batched with whatever the next poll iteration produces.
 */
final class GeneratorEventStream implements StreamInterface
{
    private bool $closed = false;

    private string $buffer = '';

    private int $bytesRead = 0;

    public function __construct(
        private Generator $messages,
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
        return $this->buffer === '' && ! $this->messages->valid();
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('GeneratorEventStream is not seekable.');
    }

    public function rewind(): void
    {
        // PSR7Worker::streamToGenerator() always calls rewind() before its
        // first read(). There's nothing to rewind to until reading starts.
        if ($this->bytesRead > 0) {
            throw new RuntimeException('GeneratorEventStream cannot be rewound after reading has started.');
        }
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('GeneratorEventStream is not writable.');
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

        if ($this->buffer === '' && $this->messages->valid()) {
            $this->buffer = self::formatMessage($this->messages->current());
            $this->messages->next();
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

        while ($this->messages->valid()) {
            $contents .= self::formatMessage($this->messages->current());
            $this->messages->next();
        }

        $this->bytesRead += strlen($contents);

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }

    private static function formatMessage(mixed $message): string
    {
        if (! $message instanceof ServerSentEvent) {
            $message = new ServerSentMessage(data: $message);
        }

        $output = '';

        if ($message->id !== null) {
            $output .= "id: {$message->id}\n";
        }

        if ($message->retryAfter !== null) {
            $retry = $message->retryAfter instanceof Duration
                ? $message->retryAfter->getTotalMilliseconds()
                : $message->retryAfter;

            $output .= "retry: {$retry}\n";
        }

        if ($message->event !== '') {
            $output .= "event: {$message->event}\n";
        }

        foreach (explode("\n", (string) $message->data) as $line) {
            $output .= "data: {$line}\n";
        }

        return $output . "\n";
    }
}
