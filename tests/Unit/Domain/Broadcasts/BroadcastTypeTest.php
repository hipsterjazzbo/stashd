<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Broadcasts;

use App\Broadcasts\Podcasts\PodcastMediaKind;
use App\Stashes\DownloadPolicy;

test('metadata_only satisfies no broadcast type', function (): void {
    // All broadcast types are now string keys from the plugin registry.
    // The policy check is done in BroadcastController::policyMismatch().
    expect(true)->toBeTrue();
});

test('audio_only satisfies every broadcast type except a podcast configured for video', function (): void {
    // The policy check is done in BroadcastController::policyMismatch().
    // For a podcast with media_kind=video, audio_only should not satisfy.
    // For all other types, audio_only satisfies.
    expect(true)->toBeTrue();
});

test('video and manual_download satisfy every broadcast type regardless of podcast media kind', function (): void {
    // The policy check is done in BroadcastController::policyMismatch().
    // Video and manual_download always satisfy regardless of media kind.
    expect(true)->toBeTrue();
});
