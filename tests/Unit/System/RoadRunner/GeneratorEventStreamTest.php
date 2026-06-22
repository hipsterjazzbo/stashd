<?php

declare(strict_types=1);

namespace Tests\Unit\System\RoadRunner;

use App\System\RoadRunner\GeneratorEventStream;
use Tempest\Http\ServerSentMessage;

function fakeMessages(array $messages): \Generator
{
    yield from $messages;
}

test('getSize is always null so RoadRunner never takes the "small enough to send as one string" shortcut', function (): void {
    $stream = new GeneratorEventStream(fakeMessages([new ServerSentMessage(data: 'one')]));

    expect($stream->getSize())->toBeNull();
});

test('read() returns one message per call without waiting to fill a larger requested length', function (): void {
    $stream = new GeneratorEventStream(fakeMessages([
        new ServerSentMessage(data: 'first', event: 'a'),
        new ServerSentMessage(data: 'second', event: 'b'),
    ]));

    $first = $stream->read(4096);
    expect($first)->toBe("event: a\ndata: \"first\"\n\n");

    $second = $stream->read(4096);
    expect($second)->toBe("event: b\ndata: \"second\"\n\n");

    expect($stream->eof())->toBeTrue();
    expect($stream->read(4096))->toBe('');
});

test('read() splits a message across calls when it exceeds the requested length, without losing or duplicating bytes', function (): void {
    $stream = new GeneratorEventStream(fakeMessages([
        new ServerSentMessage(data: 'hello', event: 'a'),
    ]));

    $formatted = "event: a\ndata: \"hello\"\n\n";
    $collected = '';

    while (! $stream->eof()) {
        $chunk = $stream->read(5);
        if ($chunk === '') {
            break;
        }
        $collected .= $chunk;
    }

    expect($collected)->toBe($formatted);
});

test('eof() is false until both the buffer and the generator are exhausted', function (): void {
    $stream = new GeneratorEventStream(fakeMessages([new ServerSentMessage(data: 'only')]));

    expect($stream->eof())->toBeFalse();
    $stream->read(1);
    expect($stream->eof())->toBeFalse();
    $stream->read(1024);
    expect($stream->eof())->toBeTrue();
});

test('getContents drains every remaining message', function (): void {
    $stream = new GeneratorEventStream(fakeMessages([
        new ServerSentMessage(data: 'one'),
        new ServerSentMessage(data: 'two'),
    ]));

    expect($stream->getContents())->toBe("event: message\ndata: \"one\"\n\nevent: message\ndata: \"two\"\n\n");
});

test('non-ServerSentEvent values are wrapped as a plain message', function (): void {
    $stream = new GeneratorEventStream(fakeMessages(['raw string']));

    expect($stream->read(4096))->toBe("event: message\ndata: \"raw string\"\n\n");
});
