<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider\YouTube;

use App\Providers\ProviderException;
use App\Providers\StashdUri;
use App\Providers\YouTube\YouTubeUriResolver;

test('youtube uri resolver accepts channel urls', function (): void {
    $input = YouTubeUriResolver::resolve(StashdUri::parse('https://www.youtube.com/channel/UCStashdDemoCh0012345678'));

    expect($input->providerKey)->toBe('youtube')
        ->and($input->inputType)->toBe('channel')
        ->and($input->providerInputId)->toBe('UCStashdDemoCh0012345678');
});

test('youtube uri resolver accepts handle urls', function (): void {
    $input = YouTubeUriResolver::resolve(StashdUri::parse('https://www.youtube.com/@StashdDemo'));

    expect($input->inputType)->toBe('channel')
        ->and($input->providerInputId)->toBe('handle:StashdDemo');
});

test('youtube uri resolver accepts playlist urls', function (): void {
    $input = YouTubeUriResolver::resolve(StashdUri::parse('https://www.youtube.com/playlist?list=PLStashdDemoPlaylist01'));

    expect($input->inputType)->toBe('playlist')
        ->and($input->providerInputId)->toBe('PLStashdDemoPlaylist01');
});

test('youtube uri resolver accepts watch and youtu.be video urls', function (): void {
    $watch = YouTubeUriResolver::resolve(StashdUri::parse('https://www.youtube.com/watch?v=demoVideo01'));
    $short = YouTubeUriResolver::resolve(StashdUri::parse('https://youtu.be/demoVideo01'));

    expect($watch->inputType)->toBe('video')
        ->and($watch->providerInputId)->toBe('demoVideo01')
        ->and($short->providerInputId)->toBe('demoVideo01');
});

test('youtube uri resolver rejects unsupported youtube urls', function (): void {
    YouTubeUriResolver::resolve(StashdUri::parse('https://www.youtube.com/feed/trending'));
})->throws(ProviderException::class);

test('youtube uri resolver rejects watch urls without video id', function (): void {
    YouTubeUriResolver::resolve(StashdUri::parse('https://www.youtube.com/watch'));
})->throws(ProviderException::class);
