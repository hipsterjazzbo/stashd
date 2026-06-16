<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider\YouTube;

use App\Providers\ProviderException;
use App\Providers\YouTube\YouTubeRssParser;

test('youtube rss parser maps atom entries to discovered items', function (): void {
    $xml = file_get_contents(__DIR__ . '/../../../../fixtures/providers/youtube/http/channel_rss.xml');
    $parser = new YouTubeRssParser();
    $items = $parser->parse($xml, 'channel');

    expect($items)->toHaveCount(3)
        ->and($items[0]->providerItemId)->toBe('demoVideo01')
        ->and($items[0]->title)->toBe('Demo Episode One')
        ->and($items[0]->canonicalUri->toString())->toBe('https://www.youtube.com/watch?v=demoVideo01')
        ->and($items[0]->thumbnailUri?->toString())->toContain('demoVideo01')
        ->and($items[0]->rawMetadata['feed_kind'])->toBe('channel');
});

test('youtube rss parser returns empty list for empty feed', function (): void {
    $xml = file_get_contents(__DIR__ . '/../../../../fixtures/providers/youtube/http/empty_feed.xml');
    $parser = new YouTubeRssParser();

    expect($parser->parse($xml, 'channel'))->toBe([]);
});

test('youtube rss parser rejects malformed xml', function (): void {
    $parser = new YouTubeRssParser();
    $parser->parse('<broken', 'channel');
})->throws(ProviderException::class);
