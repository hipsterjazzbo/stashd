<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Broadcasts;

use App\Broadcasts\BroadcastType;
use App\Stashes\DownloadPolicy;

test('metadata_only satisfies no broadcast type', function (): void {
    foreach (BroadcastType::cases() as $type) {
        expect($type->isSatisfiedByDownloadPolicy(DownloadPolicy::MetadataOnly))->toBeFalse();
    }
});

test('audio_only satisfies every broadcast type except video_podcast', function (): void {
    expect(BroadcastType::VideoPodcast->isSatisfiedByDownloadPolicy(DownloadPolicy::AudioOnly))->toBeFalse();

    foreach (BroadcastType::cases() as $type) {
        if ($type === BroadcastType::VideoPodcast) {
            continue;
        }

        expect($type->isSatisfiedByDownloadPolicy(DownloadPolicy::AudioOnly))->toBeTrue();
    }
});

test('video and manual_download satisfy every broadcast type', function (): void {
    foreach (BroadcastType::cases() as $type) {
        expect($type->isSatisfiedByDownloadPolicy(DownloadPolicy::Video))->toBeTrue()
            ->and($type->isSatisfiedByDownloadPolicy(DownloadPolicy::ManualDownload))->toBeTrue();
    }
});
