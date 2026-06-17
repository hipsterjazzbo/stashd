<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Broadcasts\BroadcastContext;
use App\Broadcasts\BroadcastContextFactory;
use App\Broadcasts\BroadcastException;
use App\Broadcasts\BroadcastItemRecord;
use App\Broadcasts\BroadcastItemRepository;
use App\Broadcasts\BroadcastItemState;
use App\Broadcasts\BroadcastPathBuilder;
use App\Broadcasts\BroadcastPlan;
use App\Broadcasts\BroadcastPlannedSidecar;
use App\Broadcasts\BroadcastPruneResult;
use App\Broadcasts\BroadcastPublishResult;
use App\Broadcasts\BroadcastSidecarType;
use App\Broadcasts\BroadcastVerifyResult;
use App\Broadcasts\Formats\BroadcastFormat;
use App\Stashes\StashItemRecord;
use App\Stashes\StashItemState;
use App\Support\PrefixedUlid;
use App\Support\RecordTimestamps;
use App\System\State\StateTransitionService;
use App\Vault\MediaItemRecord;

abstract readonly class PodcastBroadcastFormat implements BroadcastFormat
{
    public function __construct(
        private BroadcastContextFactory $contextFactory,
        private BroadcastPathBuilder $paths,
        private BroadcastItemRepository $broadcastItems,
        private PodcastAssetSelector $assets,
        private PodcastTokenService $tokens,
        private PodcastEpisodeUrlBuilder $urls,
        private PodcastGuid $guids,
        private PodcastFeedBuilder $feedBuilder,
        private StateTransitionService $transitions,
    ) {
    }

    abstract protected function selectAsset(string $mediaItemId): ?PodcastAssetSelection;

    abstract protected function unavailableErrorCode(): string;

    public function plan(BroadcastContext $context): BroadcastPlan
    {
        $broadcastId = (string) $context->broadcast->id;

        return new BroadcastPlan(
            broadcastId: $broadcastId,
            broadcastRoot: $this->paths->broadcastRoot($broadcastId),
            files: [],
            sidecars: [
                new BroadcastPlannedSidecar(
                    kind: BroadcastSidecarType::FeedXml,
                    relativePath: $this->paths->relativeFile('feed.xml'),
                    absolutePath: $this->feedPath($context),
                    content: '',
                ),
            ],
            skippedStashItemIds: $this->skippedStashItemIds($context),
        );
    }

    public function publish(BroadcastContext $context, BroadcastPlan $plan): BroadcastPublishResult
    {
        $broadcastToken = $this->tokens->ensureBroadcastToken($context->broadcast);
        $episodes = [];
        $failed = [];
        $included = 0;

        foreach ($context->stashItems as $stashItem) {
            if ($stashItem->state !== StashItemState::Active) {
                continue;
            }

            $mediaItem = $context->mediaItems[$stashItem->mediaItemId] ?? null;
            $item = $this->findOrCreateItem($context, $stashItem);

            if ($mediaItem === null) {
                $this->markItemFailed($item, 'podcast_media_item_unavailable');
                $failed[] = (string) $stashItem->id;

                continue;
            }

            $selection = $this->selectAsset($stashItem->mediaItemId);

            if ($selection === null) {
                $this->markItemFailed($item, $this->unavailableErrorCode());
                $failed[] = (string) $stashItem->id;

                continue;
            }

            $itemToken = $this->tokens->ensureItemToken($item);
            $this->markItemReady($item);
            $episodes[] = $this->episode($context, $stashItem, $mediaItem, $item, $selection, $broadcastToken, $itemToken);
            $included++;
        }

        $feedPath = $this->feedPath($context);
        $this->writeFeed($feedPath, $this->feedBuilder->build($this->metadata($context, $broadcastToken), $episodes));

        return new BroadcastPublishResult(
            publishedCount: 1,
            skippedCount: count($failed),
            publishedPaths: [$feedPath],
            failedStashItemIds: $failed,
        );
    }

    public function verify(BroadcastContext $context): BroadcastVerifyResult
    {
        $valid = [];
        $stale = [];
        $missing = [];
        $feedPath = $this->feedPath($context);

        if (! is_file($feedPath) || ! is_readable($feedPath)) {
            $missing[] = 'feed.xml';
        }

        foreach ($context->stashItems as $stashItem) {
            if ($stashItem->state !== StashItemState::Active) {
                continue;
            }

            $item = $this->broadcastItems->findByBroadcastAndStashItem(
                PrefixedUlid::parse((string) $context->broadcast->id),
                PrefixedUlid::parse((string) $stashItem->id),
            );

            if ($item === null) {
                $stale[] = (string) $stashItem->id;

                continue;
            }

            if ($this->selectAsset($stashItem->mediaItemId) === null) {
                $this->markItemStale($item, $this->unavailableErrorCode());
                $stale[] = (string) $item->id;

                continue;
            }

            if ($this->tokens->itemToken($item) === null) {
                $this->markItemStale($item, 'podcast_item_token_unavailable');
                $stale[] = (string) $item->id;

                continue;
            }

            $item->lastVerifiedAt = RecordTimestamps::now();
            $item->lastError = null;

            if ($item->state !== BroadcastItemState::Ready) {
                $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Ready);
            } else {
                $this->broadcastItems->save($item);
            }

            $valid[] = (string) $item->id;
        }

        $staleCount = count($stale) + count($missing);

        return new BroadcastVerifyResult(
            ok: $staleCount === 0 && count($valid) > 0,
            validCount: count($valid),
            staleCount: $staleCount,
            validItemIds: $valid,
            staleItemIds: $stale,
            missingItemIds: $missing,
        );
    }

    public function prune(BroadcastContext $context): BroadcastPruneResult
    {
        $feedPath = $this->feedPath($context);

        if (is_file($feedPath) && @unlink($feedPath)) {
            return new BroadcastPruneResult(removedCount: 1, removedPaths: [$feedPath]);
        }

        return new BroadcastPruneResult(removedCount: 0, removedPaths: []);
    }

    protected function audioAsset(string $mediaItemId): ?PodcastAssetSelection
    {
        return $this->assets->audioAsset($mediaItemId);
    }

    protected function videoAsset(string $mediaItemId): ?PodcastAssetSelection
    {
        return $this->assets->videoAsset($mediaItemId);
    }

    private function findOrCreateItem(BroadcastContext $context, StashItemRecord $stashItem): BroadcastItemRecord
    {
        $broadcastId = PrefixedUlid::parse((string) $context->broadcast->id);
        $item = $this->broadcastItems->findByBroadcastAndStashItem(
            $broadcastId,
            PrefixedUlid::parse((string) $stashItem->id),
        );

        return $item ?? $this->broadcastItems->create(
            broadcastId: $broadcastId,
            stashItemId: PrefixedUlid::parse((string) $stashItem->id),
            mediaItemId: PrefixedUlid::parse($stashItem->mediaItemId),
        );
    }

    private function markItemReady(BroadcastItemRecord $item): void
    {
        if ($item->state !== BroadcastItemState::Processing) {
            $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Processing);
        }

        $item->publishedPath = null;
        $item->publishedUri = null;
        $item->lastPublishedAt = RecordTimestamps::now();
        $item->lastError = null;
        $this->broadcastItems->save($item);
        $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Ready);
    }

    private function markItemFailed(BroadcastItemRecord $item, string $reason): void
    {
        if ($item->state !== BroadcastItemState::Processing && $item->state->canTransitionTo(BroadcastItemState::Processing)) {
            $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Processing);
        }

        $item->lastError = $reason;
        $this->broadcastItems->save($item);

        if ($item->state->canTransitionTo(BroadcastItemState::Failed)) {
            $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Failed);
        }
    }

    private function markItemStale(BroadcastItemRecord $item, string $reason): void
    {
        $item->lastError = $reason;
        $this->broadcastItems->save($item);

        if ($item->state->canTransitionTo(BroadcastItemState::Stale)) {
            $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Stale);
        }
    }

    private function episode(
        BroadcastContext $context,
        StashItemRecord $stashItem,
        MediaItemRecord $mediaItem,
        BroadcastItemRecord $item,
        PodcastAssetSelection $selection,
        string $broadcastToken,
        string $itemToken,
    ): PodcastEpisode {
        return new PodcastEpisode(
            guid: $this->guids->forItem($item),
            title: $this->episodeTitle($stashItem, $mediaItem),
            description: $this->episodeDescription($stashItem, $mediaItem),
            publishedAt: $mediaItem->publishedAt ?? $stashItem->firstSeenAt ?? $context->broadcast->createdAt ?? RecordTimestamps::now(),
            enclosureUrl: $this->urls->episodeUrl($broadcastToken, $itemToken, $selection->extension),
            enclosureLength: $selection->length,
            enclosureMimeType: $selection->mimeType,
        );
    }

    private function metadata(BroadcastContext $context, string $broadcastToken): PodcastFeedMetadata
    {
        $settings = $this->settings($context);
        $title = $this->nonEmptyString($settings['title'] ?? null)
            ?? $context->stash->name
            ?? $context->broadcast->name;
        $description = $this->nonEmptyString($settings['description'] ?? null)
            ?? $context->stash->description
            ?? 'Private Stashd podcast feed.';

        return new PodcastFeedMetadata(
            title: $title,
            description: $description,
            feedUrl: $this->urls->feedUrl($broadcastToken),
            linkUrl: $this->nonEmptyString($settings['link_url'] ?? null),
            author: $this->nonEmptyString($settings['author'] ?? null),
            imageUrl: $this->nonEmptyString($settings['image_url'] ?? null),
            fundingUrl: $this->nonEmptyString($settings['funding_url'] ?? null),
        );
    }

    private function episodeTitle(StashItemRecord $stashItem, MediaItemRecord $mediaItem): string
    {
        return $this->nonEmptyString($mediaItem->title)
            ?? $this->nonEmptyString($stashItem->displayTitle)
            ?? 'Untitled episode';
    }

    private function episodeDescription(StashItemRecord $stashItem, MediaItemRecord $mediaItem): string
    {
        return $this->nonEmptyString($mediaItem->description)
            ?? $this->nonEmptyString($stashItem->displayDescription)
            ?? $this->episodeTitle($stashItem, $mediaItem);
    }

    /** @return array<string, mixed> */
    private function settings(BroadcastContext $context): array
    {
        if ($context->broadcast->settingsJson === null) {
            return [];
        }

        $settings = json_decode($context->broadcast->settingsJson, true);

        return is_array($settings) ? $settings : [];
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function feedPath(BroadcastContext $context): string
    {
        return $this->paths->broadcastFile((string) $context->broadcast->id, 'feed.xml');
    }

    private function writeFeed(string $path, string $xml): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw BroadcastException::withCode('podcast_feed_write_failed', 'Podcast feed could not be written.');
        }

        if (file_put_contents($path, $xml) === false) {
            throw BroadcastException::withCode('podcast_feed_write_failed', 'Podcast feed could not be written.');
        }
    }

    /** @return list<string> */
    private function skippedStashItemIds(BroadcastContext $context): array
    {
        $skipped = [];

        foreach ($context->stashItems as $stashItem) {
            if ($stashItem->state !== StashItemState::Active) {
                $skipped[] = (string) $stashItem->id;
            }
        }

        return $skipped;
    }
}
