<?php

declare(strict_types=1);

namespace App\Transcoding;

use App\Support\DurationSeconds;
use App\Support\PrefixedUlid;
use App\System\State\StateTransitionService;
use App\Transcoding\Ffmpeg\FfmpegAudioProfile;
use App\Transcoding\Ffmpeg\FfmpegGateway;
use App\Transcoding\Ffmpeg\FfmpegProgress;
use App\Vault\AssetId;
use App\Vault\AssetRepository;
use App\Vault\AssetState;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;
use App\Vault\MoveFileIntoVault;
use App\Vault\StageDownloadFiles;
use App\Vault\VaultChecksum;
use App\Vault\VaultPathBuilder;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

/** Generates a derived MP3 podcast-audio asset by extracting audio from an existing ready video Vault original. */
final readonly class TranscodePodcastAudioAsset
{
    private const string OUTPUT_FILENAME = 'podcast-audio.mp3';

    public function __construct(
        private FfmpegGateway $ffmpeg,
        private AssetRepository $assets,
        private MediaItemRepository $mediaItems,
        private StageDownloadFiles $tempStaging,
        private VaultPathBuilder $vaultPaths,
        private MoveFileIntoVault $fileMover,
        private StateTransitionService $transitions,
    ) {
    }

    /**
     * @param ?callable(FfmpegProgress): void $onProgress
     */
    public function execute(
        MediaItemId $mediaItemId,
        AssetId $sourceAssetId,
        AssetId $audioAssetId,
        PrefixedUlid $jobId,
        ?callable $onProgress = null,
    ): TranscodePodcastAudioResult {
        $mediaItem = $this->mediaItems->find($mediaItemId)
            ?? throw TranscodeException::withCode('transcode_media_item_not_found', 'Media item not found.');

        $source = $this->assets->find($sourceAssetId);

        if ($source === null || $source->state !== AssetState::Ready || $source->path === null || ! is_file($source->path)) {
            throw TranscodeException::withCode('transcode_source_unavailable', 'Source video asset is not ready.');
        }

        $audioAsset = $this->assets->find($audioAssetId)
            ?? throw TranscodeException::withCode('transcode_asset_not_found', 'Target audio asset not found.');

        if ($audioAsset->state === AssetState::Ready) {
            return new TranscodePodcastAudioResult(
                mediaItemId: $mediaItemId->toString(),
                assetId: $audioAssetId->toString(),
                sizeBytes: $audioAsset->sizeBytes,
                durationSeconds: DurationSeconds::toSeconds($audioAsset->durationSeconds),
            );
        }

        $probe = $this->ffmpeg->probe();

        if (! $probe->available) {
            throw TranscodeException::withCode('ffmpeg_unavailable', $probe->message ?? 'ffmpeg binary is not available.');
        }

        if ($audioAsset->state !== AssetState::Processing) {
            $this->transitions->transitionAsset($audioAsset, AssetState::Processing);
        }

        $tempDirectory = $this->tempStaging->createWorkDirectory($jobId);

        try {
            $tempPath = rtrim($tempDirectory, '/') . '/' . self::OUTPUT_FILENAME;

            $this->ffmpeg->transcodeToMp3(
                sourcePath: $source->path,
                destinationPath: $tempPath,
                profile: FfmpegAudioProfile::v1Default(),
                totalSeconds: DurationSeconds::toSeconds($source->durationSeconds),
                onProgress: $onProgress,
            );

            if (! is_file($tempPath) || ! is_readable($tempPath)) {
                throw TranscodeException::withCode('transcode_missing_output', 'Transcoded file is missing or unreadable before Vault ingest.');
            }

            $checksum = VaultChecksum::computeFile($tempPath);
            $sizeBytes = filesize($tempPath);
            $destination = $this->vaultPaths->vaultFile($mediaItem->providerKey, $mediaItem->providerItemId, self::OUTPUT_FILENAME);

            $this->fileMover->moveIntoPlace($tempPath, $destination);

            $audioAsset->path = $destination;
            $audioAsset->relativePath = $this->vaultPaths->relativeFile($mediaItem->providerKey, $mediaItem->providerItemId, self::OUTPUT_FILENAME);
            $audioAsset->mimeType = 'audio/mpeg';
            $audioAsset->container = 'mp3';
            $audioAsset->sizeBytes = is_int($sizeBytes) ? $sizeBytes : null;
            $audioAsset->checksum = $checksum;
            $audioAsset->durationSeconds = $source->durationSeconds;
            $audioAsset->derivedFromAssetId = $sourceAssetId;
            $audioAsset->lastVerifiedAt = DateTime::now(Timezone::UTC);
            $this->assets->save($audioAsset);
            $this->transitions->transitionAsset($audioAsset, AssetState::Ready);

            $this->tempStaging->cleanupSuccess($tempDirectory);

            return new TranscodePodcastAudioResult(
                mediaItemId: $mediaItemId->toString(),
                assetId: $audioAssetId->toString(),
                sizeBytes: $audioAsset->sizeBytes,
                durationSeconds: DurationSeconds::toSeconds($audioAsset->durationSeconds),
            );
        } catch (\Throwable $throwable) {
            $this->tempStaging->markFailed($tempDirectory);

            if ($audioAsset->state === AssetState::Processing) {
                $this->transitions->transitionAsset($audioAsset, AssetState::Failed);
            }

            if ($throwable instanceof TranscodeException) {
                throw $throwable;
            }

            throw TranscodeException::withCode('transcode_failed', $throwable->getMessage(), $throwable);
        }
    }
}
