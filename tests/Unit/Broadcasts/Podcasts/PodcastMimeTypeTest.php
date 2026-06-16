<?php

declare(strict_types=1);

namespace Tests\Unit\Broadcasts\Podcasts;

use App\Broadcasts\Podcasts\PodcastMimeType;
use App\Vault\AssetKind;
use App\Vault\AssetRecord;
use App\Vault\AssetRole;
use App\Vault\AssetState;

test('podcast mime type prefers stored supported mime type', function (): void {
    $asset = new AssetRecord(
        role: AssetRole::VaultOriginal,
        kind: AssetKind::Audio,
        state: AssetState::Ready,
        path: '/vault/original.bin',
        mimeType: 'audio/mpeg',
    );

    expect((new PodcastMimeType())->forAudioAsset($asset))->toBe('audio/mpeg');
});

test('podcast mime type infers conservative audio and video types from extension', function (): void {
    $audio = new AssetRecord(
        role: AssetRole::VaultOriginal,
        kind: AssetKind::Audio,
        state: AssetState::Ready,
        path: '/vault/original.m4a',
    );
    $video = new AssetRecord(
        role: AssetRole::VaultOriginal,
        kind: AssetKind::Video,
        state: AssetState::Ready,
        path: '/vault/original.mp4',
    );

    $mime = new PodcastMimeType();

    expect($mime->forAudioAsset($audio))->toBe('audio/mp4')
        ->and($mime->forVideoAsset($video))->toBe('video/mp4');
});

test('podcast mime type rejects unsuitable video containers', function (): void {
    $asset = new AssetRecord(
        role: AssetRole::VaultOriginal,
        kind: AssetKind::Video,
        state: AssetState::Ready,
        path: '/vault/original.mkv',
        mimeType: 'video/x-matroska',
    );

    expect((new PodcastMimeType())->forVideoAsset($asset))->toBeNull();
});
