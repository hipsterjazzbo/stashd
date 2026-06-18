<?php

declare(strict_types=1);

namespace Tests\Unit\Broadcasts\Podcasts;

use App\Broadcasts\Podcasts\PodcastFundingLinkDetector;

test('detects a patreon link', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['Support the show: https://www.patreon.com/example']))
        ->toBe('https://www.patreon.com/example');
});

test('detects a ko-fi link', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['Buy me a coffee at https://ko-fi.com/example']))
        ->toBe('https://ko-fi.com/example');
});

test('detects a github sponsors link', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['Sponsor me: https://github.com/sponsors/example']))
        ->toBe('https://github.com/sponsors/example');
});

test('detects a buy me a coffee link', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['https://buymeacoffee.com/example']))
        ->toBe('https://buymeacoffee.com/example');
});

test('priority order beats textual order', function (): void {
    $detector = new PodcastFundingLinkDetector();

    $result = $detector->detect([
        'First mentioned: https://buymeacoffee.com/example',
        'Later mentioned: https://www.patreon.com/example',
    ]);

    expect($result)->toBe('https://www.patreon.com/example');
});

test('first encountered wins within the same priority class', function (): void {
    $detector = new PodcastFundingLinkDetector();

    $result = $detector->detect([
        'https://www.patreon.com/first',
        'https://www.patreon.com/second',
    ]);

    expect($result)->toBe('https://www.patreon.com/first');
});

test('strips trailing punctuation from plain text descriptions', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['Support us (https://www.patreon.com/example).']))
        ->toBe('https://www.patreon.com/example');
});

test('handles markdown style links', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['[Support on Patreon](https://www.patreon.com/example)']))
        ->toBe('https://www.patreon.com/example');
});

test('rejects deceptive hosts', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['https://evilpatreon.com/example']))->toBeNull()
        ->and($detector->detect(['https://patreon.com.evil.example/example']))->toBeNull();
});

test('rejects non-http schemes', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['ftp://patreon.com/example']))->toBeNull()
        ->and($detector->detect(['mailto:creator@patreon.com']))->toBeNull();
});

test('returns null when no supported funding link is present', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['Just a regular episode description.', null]))->toBeNull();
});

test('normalizes http to https for recognized funding domains', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['http://www.patreon.com/example']))
        ->toBe('https://www.patreon.com/example');
});

test('github sponsors requires a sponsors path segment', function (): void {
    $detector = new PodcastFundingLinkDetector();

    expect($detector->detect(['https://github.com/example']))->toBeNull();
});
