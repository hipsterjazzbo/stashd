<?php

declare(strict_types=1);

namespace Tests\Unit\Broadcasts\Podcasts;

use App\Broadcasts\Podcasts\PodcastEpisode;
use App\Broadcasts\Podcasts\PodcastFeedBuilder;
use App\Broadcasts\Podcasts\PodcastFeedMetadata;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

test('podcast feed builder emits valid escaped deterministic rss', function (): void {
    $builder = new PodcastFeedBuilder();
    $metadata = new PodcastFeedMetadata(
        title: 'A & B <Show>',
        description: 'Private "feed" & archive',
        feedUrl: 'http://localhost:8474/b/feed-token/feed.xml',
        author: 'Author & Co',
    );
    $episodes = [
        new PodcastEpisode(
            guid: 'stashd:broadcast:one:item:two',
            title: 'Episode <Two>',
            description: 'Description & details',
            publishedAt: DateTime::parse('2026-01-02T12:00:00Z', Timezone::UTC),
            enclosureUrl: 'http://localhost:8474/b/feed-token/items/item-token/episode.mp3',
            enclosureLength: 456,
            enclosureMimeType: 'audio/mpeg',
        ),
        new PodcastEpisode(
            guid: 'stashd:broadcast:one:item:one',
            title: 'Episode One',
            description: 'First',
            publishedAt: DateTime::parse('2026-01-01T12:00:00Z', Timezone::UTC),
            enclosureUrl: 'http://localhost:8474/b/feed-token/items/item-token-1/episode.mp3',
            enclosureLength: 123,
            enclosureMimeType: 'audio/mpeg',
        ),
    ];

    $first = $builder->build($metadata, $episodes);
    $second = $builder->build($metadata, array_reverse($episodes));

    expect(simplexml_load_string($first))->not->toBeFalse()
        ->and($first)->toBe($second)
        ->and($first)->toContain('<title>A &amp; B &lt;Show&gt;</title>')
        ->and($first)->toContain('url="http://localhost:8474/b/feed-token/items/item-token-1/episode.mp3"')
        ->and($first)->toContain('length="123"')
        ->and($first)->toContain('type="audio/mpeg"')
        ->and(strpos($first, 'Episode One'))->toBeLessThan(strpos($first, 'Episode &lt;Two&gt;'));
});
