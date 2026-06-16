<?php

declare(strict_types=1);

namespace App\Services\Vault;

use App\Domain\Media\AssetRecord;
use App\Domain\Media\AssetRole;
use App\Domain\Media\AssetState;
use App\Domain\Media\MediaItemState;
use App\Domain\Storage\StorageLocationKey;
use App\Domain\Storage\StorageLocationState;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\AssetRepository;
use App\Infrastructure\Persistence\MediaItemRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Infrastructure\Persistence\StorageLocationRepository;
use App\Services\State\StateTransitionService;

final readonly class VaultVerifyService
{
    public function __construct(
        private AssetRepository $assets,
        private MediaItemRepository $mediaItems,
        private StorageLocationRepository $storageLocations,
        private StateTransitionService $transitions,
    ) {
    }

    public function verifyAll(): VaultVerifyResult
    {
        $vault = $this->storageLocations->findByKey(StorageLocationKey::Vault);

        if ($vault !== null && in_array($vault->state, [StorageLocationState::Unavailable, StorageLocationState::Missing], true)) {
            return new VaultVerifyResult(
                checked: 0,
                missing: 0,
                restored: 0,
                checksumMismatch: 0,
                storageUnavailable: true,
            );
        }

        $checked = 0;
        $missing = 0;
        $restored = 0;
        $checksumMismatch = 0;

        foreach ($this->assets->listReadyVaultAssets() as $asset) {
            $checked++;
            $result = $this->verifyAssetRecord($asset);

            match ($result) {
                VerifyAssetOutcome::Missing => $missing++,
                VerifyAssetOutcome::Restored => $restored++,
                VerifyAssetOutcome::ChecksumMismatch => $checksumMismatch++,
                default => null,
            };
        }

        return new VaultVerifyResult(
            checked: $checked,
            missing: $missing,
            restored: $restored,
            checksumMismatch: $checksumMismatch,
            storageUnavailable: false,
        );
    }

    public function verifyAsset(PrefixedUlid $assetId): VerifyAssetOutcome
    {
        $asset = $this->assets->find($assetId);

        if ($asset === null) {
            return VerifyAssetOutcome::NotFound;
        }

        $vault = $this->storageLocations->findByKey(StorageLocationKey::Vault);

        if ($vault !== null && in_array($vault->state, [StorageLocationState::Unavailable, StorageLocationState::Missing], true)) {
            return VerifyAssetOutcome::StorageUnavailable;
        }

        return $this->verifyAssetRecord($asset);
    }

    private function verifyAssetRecord(AssetRecord $asset): VerifyAssetOutcome
    {
        if ($asset->path === null) {
            return VerifyAssetOutcome::Skipped;
        }

        if (! is_file($asset->path) || ! is_readable($asset->path)) {
            return $this->markMissing($asset);
        }

        if ($asset->checksum !== null && ! VaultChecksum::verifyFile($asset->path, $asset->checksum)) {
            return $this->markChecksumMismatch($asset);
        }

        $asset->lastVerifiedAt = RecordTimestamps::now();
        $asset->missingAt = null;
        $asset->missingReason = null;

        if ($asset->state === AssetState::Missing || $asset->state === AssetState::Stale) {
            $this->transitions->transitionAsset($asset, AssetState::Ready);
            $this->syncMediaItemAfterAssetRestore($asset);
            $this->assets->save($asset);

            return VerifyAssetOutcome::Restored;
        }

        $this->assets->save($asset);

        return VerifyAssetOutcome::Ok;
    }

    private function markMissing(AssetRecord $asset): VerifyAssetOutcome
    {
        if ($asset->state !== AssetState::Missing) {
            $this->transitions->transitionAsset($asset, AssetState::Missing);
        }

        $asset->missingAt = RecordTimestamps::now();
        $asset->missingReason = 'vault_file_missing';
        $this->assets->save($asset);
        $this->syncMediaItemAfterAssetMissing($asset);

        return VerifyAssetOutcome::Missing;
    }

    private function markChecksumMismatch(AssetRecord $asset): VerifyAssetOutcome
    {
        if ($asset->state !== AssetState::Stale) {
            $this->transitions->transitionAsset($asset, AssetState::Stale);
        }

        $asset->missingAt = RecordTimestamps::now();
        $asset->missingReason = 'checksum_mismatch';
        $this->assets->save($asset);

        return VerifyAssetOutcome::ChecksumMismatch;
    }

    private function syncMediaItemAfterAssetMissing(AssetRecord $asset): void
    {
        if ($asset->mediaItemId === null || $asset->role !== AssetRole::VaultOriginal) {
            return;
        }

        $mediaItem = $this->mediaItems->find(PrefixedUlid::parse($asset->mediaItemId));

        if ($mediaItem === null) {
            return;
        }

        if ($mediaItem->state === MediaItemState::Ready) {
            $this->transitions->transitionMediaItem($mediaItem, MediaItemState::Missing);
        } elseif ($mediaItem->state === MediaItemState::Failed) {
            $this->transitions->transitionMediaItem($mediaItem, MediaItemState::Missing);
        }
    }

    private function syncMediaItemAfterAssetRestore(AssetRecord $asset): void
    {
        if ($asset->mediaItemId === null || $asset->role !== AssetRole::VaultOriginal) {
            return;
        }

        $mediaItem = $this->mediaItems->find(PrefixedUlid::parse($asset->mediaItemId));

        if ($mediaItem === null) {
            return;
        }

        if ($mediaItem->state === MediaItemState::Missing) {
            $this->transitions->transitionMediaItem($mediaItem, MediaItemState::Ready);
        }
    }
}
