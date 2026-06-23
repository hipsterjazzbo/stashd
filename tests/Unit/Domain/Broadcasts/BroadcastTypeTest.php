<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Broadcasts;

use App\Broadcasts\BroadcastType;
use App\Broadcasts\Podcasts\PodcastMediaKind;
use App\Stashes\DownloadPolicy;

test('metadata_only satisfies no broadcast type', function (): void {
    foreach (BroadcastType::cases() as $type) {
        expect($type->isSatisfiedByDownloadPolicy(DownloadPolicy::MetadataOnly))->toBeFalse();
    }
});

test('audio_only satisfies every broadcast type except a podcast configured for video', function (): void {
    expect(BroadcastType::Podcast->isSatisfiedByDownloadPolicy(DownloadPolicy::AudioOnly, PodcastMediaKind::Video))->toBeFalse()
        ->and(BroadcastType::Podcast->isSatisfiedByDownloadPolicy(DownloadPolicy::AudioOnly, PodcastMediaKind::Audio))->toBeTrue();

    foreach (BroadcastType::cases() as $type) {
        if ($type === BroadcastType::Podcast) {
            continue;
        }

        expect($type->isSatisfiedByDownloadPolicy(DownloadPolicy::AudioOnly))->toBeTrue();
    }
});

test('video and manual_download satisfy every broadcast type regardless of podcast media kind', function (): void {
    foreach (BroadcastType::cases() as $type) {
        expect($type->isSatisfiedByDownloadPolicy(DownloadPolicy::Video))->toBeTrue()
            ->and($type->isSatisfiedByDownloadPolicy(DownloadPolicy::ManualDownload))->toBeTrue();
    }

    expect(BroadcastType::Podcast->isSatisfiedByDownloadPolicy(DownloadPolicy::Video, PodcastMediaKind::Video))->toBeTrue()
        ->and(BroadcastType::Podcast->isSatisfiedByDownloadPolicy(DownloadPolicy::ManualDownload, PodcastMediaKind::Video))->toBeTrue();
});
