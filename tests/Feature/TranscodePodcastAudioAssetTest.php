<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PrefixedUlid;
use App\Transcoding\Ffmpeg\FfmpegGateway;
use App\Transcoding\Ffmpeg\StubFfmpegGateway;
use App\Transcoding\TranscodeException;
use App\Transcoding\TranscodePodcastAudioAsset;
use App\Vault\AssetId;
use App\Vault\AssetKind;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;

test('a successful transcode produces a ready derived audio asset', function (): void {
    [$mediaItemId, $videoAssetId, $audioAssetId] = transcodeExecutorTestFixture($this->container->get(MediaItemRepository::class), $this->container->get(AssetRepository::class), 'success');

    $gateway = $this->container->get(FfmpegGateway::class);
    assert($gateway instanceof StubFfmpegGateway);
    $gateway->transcodeCalls = 0; // the testing gateway is a process-lifetime singleton, shared across tests

    $executor = $this->container->get(TranscodePodcastAudioAsset::class);
    $result = $executor->execute(
        mediaItemId: MediaItemId::parse($mediaItemId),
        sourceAssetId: AssetId::parse($videoAssetId),
        audioAssetId: AssetId::parse($audioAssetId),
        jobId: PrefixedUlid::parse('job_01J00000000000000000000099'),
    );

    $audioAsset = $this->container->get(AssetRepository::class)->find(AssetId::parse($audioAssetId));

    expect($gateway->transcodeCalls)->toBe(1)
        ->and($audioAsset->state)->toBe(AssetState::Ready)
        ->and((string) $audioAsset->derivedFromAssetId)->toBe($videoAssetId)
        ->and($audioAsset->mimeType)->toBe('audio/mpeg')
        ->and($audioAsset->container)->toBe('mp3')
        ->and($audioAsset->path)->not->toBeNull()
        ->and(is_file($audioAsset->path))->toBeTrue()
        ->and($audioAsset->checksum)->not->toBeNull()
        ->and($result->assetId)->toBe($audioAssetId);
});

test('re-entry on an already ready asset short-circuits without transcoding again', function (): void {
    [$mediaItemId, $videoAssetId, $audioAssetId] = transcodeExecutorTestFixture($this->container->get(MediaItemRepository::class), $this->container->get(AssetRepository::class), 'idempotent');

    $gateway = $this->container->get(FfmpegGateway::class);
    assert($gateway instanceof StubFfmpegGateway);
    $gateway->transcodeCalls = 0;

    $executor = $this->container->get(TranscodePodcastAudioAsset::class);
    $executor->execute(
        mediaItemId: MediaItemId::parse($mediaItemId),
        sourceAssetId: AssetId::parse($videoAssetId),
        audioAssetId: AssetId::parse($audioAssetId),
        jobId: PrefixedUlid::parse('job_01J00000000000000000000098'),
    );

    expect($gateway->transcodeCalls)->toBe(1);

    $executor->execute(
        mediaItemId: MediaItemId::parse($mediaItemId),
        sourceAssetId: AssetId::parse($videoAssetId),
        audioAssetId: AssetId::parse($audioAssetId),
        jobId: PrefixedUlid::parse('job_01J00000000000000000000097'),
    );

    expect($gateway->transcodeCalls)->toBe(1);
});

test('a failed transcode leaves the asset Failed and marks temp staging failed', function (): void {
    [$mediaItemId, $videoAssetId, $audioAssetId] = transcodeExecutorTestFixture($this->container->get(MediaItemRepository::class), $this->container->get(AssetRepository::class), 'failure');

    $gateway = $this->container->get(FfmpegGateway::class);
    assert($gateway instanceof StubFfmpegGateway);
    $gateway->failNextTranscode = true;

    $executor = $this->container->get(TranscodePodcastAudioAsset::class);
    $jobId = PrefixedUlid::parse('job_01J00000000000000000000096');

    expect(fn () => $executor->execute(
        mediaItemId: MediaItemId::parse($mediaItemId),
        sourceAssetId: AssetId::parse($videoAssetId),
        audioAssetId: AssetId::parse($audioAssetId),
        jobId: $jobId,
    ))->toThrow(TranscodeException::class);

    $audioAsset = $this->container->get(AssetRepository::class)->find(AssetId::parse($audioAssetId));
    $config = $this->container->get(\App\Config\StashdConfig::class);
    $tempDirectory = rtrim($config->tempPath(), '/') . '/downloads/' . $jobId->toString();

    // markFailed() leaves a .failed marker rather than removing the
    // directory outright -- StageDownloadFiles::createWorkDirectory()
    // cleans it on the next attempt for the same job id.
    expect($audioAsset->state)->toBe(AssetState::Failed)
        ->and(is_file($tempDirectory . '/.failed'))->toBeTrue();
});

/**
 * @return array{0: string, 1: string, 2: string} [mediaItemId, videoAssetId, audioAssetId]
 */
function transcodeExecutorTestFixture(MediaItemRepository $mediaItems, AssetRepository $assets, string $slug): array
{
    $media = $mediaItems->create(
        providerKey: 'fake',
        providerItemId: 'transcode-executor-' . $slug,
        canonicalUri: 'fake://item/transcode-executor-' . $slug,
        title: 'Transcode Executor Fixture',
        durationSeconds: 90,
    );

    $tempPath = sys_get_temp_dir() . '/stashd-transcode-executor-' . $slug . '.mp4';
    file_put_contents($tempPath, 'fake-video-bytes');

    $videoAsset = $assets->create(
        mediaItemId: MediaItemId::parse((string) $media->id),
        role: AssetRole::VaultOriginal,
        kind: AssetKind::Video,
        state: AssetState::Ready,
        path: $tempPath,
    );

    $audioAsset = $assets->create(
        mediaItemId: MediaItemId::parse((string) $media->id),
        role: AssetRole::PodcastAudio,
        kind: AssetKind::Audio,
        state: AssetState::Pending,
    );

    return [(string) $media->id, (string) $videoAsset->id, (string) $audioAsset->id];
}
