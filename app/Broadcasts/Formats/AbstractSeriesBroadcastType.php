<?php

declare(strict_types=1);

namespace App\Broadcasts\Formats;

use App\Broadcasts\BroadcastContext;
use App\Broadcasts\BroadcastContextFactory;
use App\Broadcasts\BroadcastFilenameBuilder;
use App\Broadcasts\BroadcastItemRepository;
use App\Broadcasts\BroadcastItemState;
use App\Broadcasts\BroadcastNfoBuilder;
use App\Broadcasts\BroadcastPathBuilder;
use App\Broadcasts\BroadcastPlan;
use App\Broadcasts\BroadcastPlannedFile;
use App\Broadcasts\BroadcastPlannedSidecar;
use App\Broadcasts\BroadcastPruneResult;
use App\Broadcasts\BroadcastPublishResult;
use App\Broadcasts\BroadcastSidecarType;
use App\Broadcasts\BroadcastSidecarWriter;
use App\Broadcasts\BroadcastVerifyResult;
use App\Broadcasts\HardlinkPublisher;
use App\Support\PrefixedUlid;
use App\System\State\StateTransitionService;
use App\Vault\AssetKind;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

abstract readonly class AbstractSeriesBroadcastType implements BroadcastFormat
{
    public function __construct(
        protected BroadcastContextFactory $contextFactory,
        protected BroadcastPathBuilder $paths,
        protected BroadcastFilenameBuilder $filenames,
        protected BroadcastNfoBuilder $nfos,
        protected BroadcastSidecarWriter $sidecarWriter,
        protected HardlinkPublisher $hardlinks,
        protected BroadcastItemRepository $broadcastItems,
        protected AssetRepository $assets,
        protected StateTransitionService $transitions,
    ) {
    }

    abstract protected function profile(): SeriesFormatOptions;

    public function plan(BroadcastContext $context): BroadcastPlan
    {
        $profile = $this->profile();
        $broadcastId = (string) $context->broadcast->id;
        $root = $this->paths->broadcastRoot($broadcastId);
        $files = [];
        $sidecars = [];
        $skipped = [];
        $position = 0;
        $tvShowNfoAdded = false;

        foreach ($this->contextFactory->publishableStashItems($context) as $stashItem) {
            $vault = $context->vaultOriginals[$stashItem->mediaItemId] ?? null;
            $mediaItem = $context->mediaItems[$stashItem->mediaItemId] ?? null;

            if ($vault === null || $mediaItem === null || $vault->path === null) {
                $skipped[] = (string) $stashItem->id;

                continue;
            }

            $position++;
            $season = $this->filenames->seasonFolder($stashItem);
            $filename = $profile->mediaServerEpisodeNaming
                ? $this->filenames->mediaServerEpisodeFilename($stashItem, $mediaItem, $vault->path, $position)
                : $this->filenames->episodeFilename($stashItem, $mediaItem, $vault->path, $position);
            $relative = $this->paths->relativeFile($season, $filename);
            $absolute = $this->paths->broadcastFile($broadcastId, $season, $filename);

            $files[] = new BroadcastPlannedFile(
                stashItemId: (string) $stashItem->id,
                mediaItemId: $stashItem->mediaItemId,
                sourceAssetId: (string) $vault->id,
                sourcePath: $vault->path,
                relativePath: $relative,
                absolutePath: $absolute,
                filename: $filename,
            );

            if ($profile->generateNfo) {
                if (! $tvShowNfoAdded) {
                    $tvShowRelative = $this->paths->relativeFile('tvshow.nfo');
                    $sidecars[] = new BroadcastPlannedSidecar(
                        kind: BroadcastSidecarType::TvShowNfo,
                        relativePath: $tvShowRelative,
                        absolutePath: $this->paths->broadcastFile($broadcastId, 'tvshow.nfo'),
                        content: $this->nfos->tvShowNfo($context->broadcast->name),
                    );
                    $tvShowNfoAdded = true;
                }

                $seasonNumber = max(1, $stashItem->seasonNumber ?? 1);
                $episodeNumber = max(1, $stashItem->episodeNumber ?? $position);
                $episodeNfoName = $this->nfos->episodeNfoFilename($filename);
                $sidecars[] = new BroadcastPlannedSidecar(
                    kind: BroadcastSidecarType::EpisodeNfo,
                    relativePath: $this->paths->relativeFile($season, $episodeNfoName),
                    absolutePath: $this->paths->broadcastFile($broadcastId, $season, $episodeNfoName),
                    content: $this->nfos->episodeNfo($stashItem, $mediaItem, $seasonNumber, $episodeNumber),
                    stashItemId: (string) $stashItem->id,
                    mediaItemId: $stashItem->mediaItemId,
                );
            }
        }

        if ($profile->attemptPosterHardlink) {
            $posterSidecar = $this->planPosterSidecar($context, $broadcastId);

            if ($posterSidecar !== null) {
                $sidecars[] = $posterSidecar;
            }
        }

        return new BroadcastPlan(
            broadcastId: $broadcastId,
            broadcastRoot: $root,
            files: $files,
            sidecars: $sidecars,
            skippedStashItemIds: $skipped,
        );
    }

    public function publish(BroadcastContext $context, BroadcastPlan $plan): BroadcastPublishResult
    {
        $broadcastId = PrefixedUlid::parse($plan->broadcastId);
        $publishedPaths = [];
        $failedStashItemIds = [];
        $publishedCount = 0;

        foreach ($plan->files as $planned) {
            $item = $this->broadcastItems->findByBroadcastAndStashItem(
                $broadcastId,
                PrefixedUlid::parse($planned->stashItemId),
            );

            if ($item === null) {
                $item = $this->broadcastItems->create(
                    broadcastId: $broadcastId,
                    stashItemId: PrefixedUlid::parse($planned->stashItemId),
                    mediaItemId: PrefixedUlid::parse($planned->mediaItemId),
                );
            }

            if ($item->state !== BroadcastItemState::Processing) {
                $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Processing);
            }

            try {
                $this->hardlinks->publishHardlink($planned->sourcePath, $planned->absolutePath);

                $item->publishedPath = $planned->absolutePath;
                $item->publishedUri = null;
                $item->lastPublishedAt = DateTime::now(Timezone::UTC);
                $item->lastError = null;
                $this->broadcastItems->save($item);

                $this->upsertHardlinkAsset(
                    broadcastId: $broadcastId,
                    broadcastItemId: PrefixedUlid::parse((string) $item->id),
                    mediaItemId: PrefixedUlid::parse($planned->mediaItemId),
                    sourceAssetId: PrefixedUlid::parse($planned->sourceAssetId),
                    path: $planned->absolutePath,
                    relativePath: $planned->relativePath,
                    sourcePath: $planned->sourcePath,
                );

                $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Ready);
                $publishedPaths[] = $planned->absolutePath;
                $publishedCount++;
            } catch (\App\Broadcasts\BroadcastException $exception) {
                $item->lastError = $exception->errorCode . ': ' . $exception->getMessage();
                $this->broadcastItems->save($item);
                $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Failed);
                $failedStashItemIds[] = $planned->stashItemId;

                throw $exception;
            }
        }

        foreach ($plan->sidecars as $sidecar) {
            if ($sidecar->kind === BroadcastSidecarType::Poster) {
                $this->publishPosterSidecar($sidecar);

                continue;
            }

            $this->sidecarWriter->write($sidecar->absolutePath, $sidecar->content);
            $publishedPaths[] = $sidecar->absolutePath;
        }

        return new BroadcastPublishResult(
            publishedCount: $publishedCount,
            skippedCount: 0,
            publishedPaths: $publishedPaths,
            failedStashItemIds: $failedStashItemIds,
        );
    }

    public function verify(BroadcastContext $context): BroadcastVerifyResult
    {
        $broadcastId = (string) $context->broadcast->id;
        $validItemIds = [];
        $staleItemIds = [];
        $missingItemIds = [];
        $invalidLinkItemIds = [];

        $plan = $this->plan($context);
        $plannedByStashItem = [];

        foreach ($plan->files as $file) {
            $plannedByStashItem[$file->stashItemId] = $file;
        }

        foreach ($this->broadcastItems->listForBroadcast(PrefixedUlid::parse($broadcastId)) as $item) {
            $planned = $plannedByStashItem[$item->stashItemId] ?? null;

            if ($planned === null) {
                if ($item->publishedPath !== null && is_file($item->publishedPath)) {
                    $staleItemIds[] = (string) $item->id;
                    $this->markItemStale($item, 'source_item_no_longer_publishable');
                } elseif ($item->publishedPath !== null) {
                    $missingItemIds[] = (string) $item->id;
                    $this->markItemStale($item, 'generated_file_missing');
                }

                continue;
            }

            $path = $item->publishedPath ?? $planned->absolutePath;

            if (! is_file($path)) {
                $missingItemIds[] = (string) $item->id;
                $this->markItemStale($item, 'generated_file_missing');

                continue;
            }

            if (! $this->hardlinks->verifyHardlink($planned->sourcePath, $path)) {
                $invalidLinkItemIds[] = (string) $item->id;
                $this->markItemStale($item, 'hardlink_target_invalid');

                continue;
            }

            if ($item->state !== BroadcastItemState::Ready) {
                $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Ready);
            }

            $item->lastVerifiedAt = DateTime::now(Timezone::UTC);
            $item->lastError = null;
            $this->broadcastItems->save($item);
            $validItemIds[] = (string) $item->id;
        }

        foreach ($plan->sidecars as $sidecar) {
            if ($sidecar->kind === BroadcastSidecarType::Poster) {
                continue;
            }

            if (! is_file($sidecar->absolutePath)) {
                $staleItemIds[] = 'sidecar:' . $sidecar->relativePath;
            }
        }

        $staleCount = count($staleItemIds) + count($missingItemIds) + count($invalidLinkItemIds);

        return new BroadcastVerifyResult(
            ok: $staleCount === 0 && count($validItemIds) > 0,
            validCount: count($validItemIds),
            staleCount: $staleCount,
            validItemIds: $validItemIds,
            staleItemIds: $staleItemIds,
            missingItemIds: $missingItemIds,
            invalidLinkItemIds: $invalidLinkItemIds,
        );
    }

    public function prune(BroadcastContext $context): BroadcastPruneResult
    {
        $broadcastId = (string) $context->broadcast->id;
        $root = $this->paths->broadcastRoot($broadcastId);
        $plan = $this->plan($context);
        $keepPaths = array_map(static fn (BroadcastPlannedFile $file): string => $file->absolutePath, $plan->files);

        foreach ($plan->sidecars as $sidecar) {
            $keepPaths[] = $sidecar->absolutePath;
        }

        $keepLookup = array_fill_keys($keepPaths, true);
        $removed = [];

        if (! is_dir($root)) {
            return new BroadcastPruneResult(removedCount: 0, removedPaths: []);
        }

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if (isset($keepLookup[$path])) {
                continue;
            }

            if (@unlink($path)) {
                $removed[] = $path;
            }
        }

        $this->pruneEmptyDirectories($root);

        return new BroadcastPruneResult(
            removedCount: count($removed),
            removedPaths: $removed,
        );
    }

    private function planPosterSidecar(BroadcastContext $context, string $broadcastId): ?BroadcastPlannedSidecar
    {
        foreach ($context->stashItems as $stashItem) {
            $thumbnail = $this->assets->findByMediaItemAndRole(
                PrefixedUlid::parse($stashItem->mediaItemId),
                AssetRole::SourceThumbnail,
            );

            if (
                $thumbnail === null
                || $thumbnail->state !== AssetState::Ready
                || $thumbnail->path === null
                || ! is_file($thumbnail->path)
            ) {
                continue;
            }

            $extension = pathinfo($thumbnail->path, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'poster.' . $extension;

            return new BroadcastPlannedSidecar(
                kind: BroadcastSidecarType::Poster,
                relativePath: $this->paths->relativeFile($filename),
                absolutePath: $this->paths->broadcastFile($broadcastId, $filename),
                content: '',
                mediaItemId: $stashItem->mediaItemId,
            );
        }

        return null;
    }

    private function publishPosterSidecar(BroadcastPlannedSidecar $sidecar): void
    {
        if ($sidecar->mediaItemId === null) {
            return;
        }

        $thumbnail = $this->assets->findByMediaItemAndRole(
            PrefixedUlid::parse($sidecar->mediaItemId),
            AssetRole::SourceThumbnail,
        );

        if ($thumbnail?->path === null || ! is_file($thumbnail->path)) {
            return;
        }

        try {
            $this->hardlinks->publishHardlink($thumbnail->path, $sidecar->absolutePath);
        } catch (\App\Broadcasts\BroadcastException) {
            // Poster is optional — skip when hardlink unavailable.
        }
    }

    private function upsertHardlinkAsset(
        PrefixedUlid $broadcastId,
        PrefixedUlid $broadcastItemId,
        PrefixedUlid $mediaItemId,
        PrefixedUlid $sourceAssetId,
        string $path,
        string $relativePath,
        string $sourcePath,
    ): void {
        $existing = \App\Vault\AssetRecord::select()
            ->where('broadcastItemId = ? AND role = ?', $broadcastItemId->toString(), AssetRole::Hardlink)
            ->first();

        $sizeBytes = is_file($sourcePath) ? filesize($sourcePath) : null;

        if ($existing === null) {
            $asset = $this->assets->create(
                mediaItemId: $mediaItemId,
                role: AssetRole::Hardlink,
                kind: AssetKind::Video,
                state: AssetState::Ready,
                path: $path,
                relativePath: $relativePath,
                sizeBytes: is_int($sizeBytes) ? $sizeBytes : null,
            );
            $asset->broadcastId = $broadcastId->toString();
            $asset->broadcastItemId = $broadcastItemId->toString();
            $asset->derivedFromAssetId = $sourceAssetId->toString();
            $this->assets->save($asset);

            return;
        }

        $existing->path = $path;
        $existing->relativePath = $relativePath;
        $existing->derivedFromAssetId = $sourceAssetId->toString();
        $existing->sizeBytes = is_int($sizeBytes) ? $sizeBytes : $existing->sizeBytes;
        $existing->lastVerifiedAt = DateTime::now(Timezone::UTC);
        $existing->missingAt = null;
        $existing->missingReason = null;

        if ($existing->state !== AssetState::Ready) {
            $this->transitions->transitionAsset($existing, AssetState::Ready);
        } else {
            $this->assets->save($existing);
        }
    }

    private function markItemStale(\App\Broadcasts\BroadcastItemRecord $item, string $reason): void
    {
        $item->lastError = $reason;

        if ($item->state !== BroadcastItemState::Stale) {
            $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Stale);
        } else {
            $this->broadcastItems->save($item);
        }
    }

    private function pruneEmptyDirectories(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        /** @var SplFileInfo $entry */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entry) {
            if (! $entry->isDir()) {
                continue;
            }

            $path = $entry->getPathname();

            if ($path === $root) {
                continue;
            }

            @rmdir($path);
        }
    }
}
