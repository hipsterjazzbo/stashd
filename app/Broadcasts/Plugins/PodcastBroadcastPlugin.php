<?php

declare(strict_types=1);

namespace App\Broadcasts\Plugins;

use App\Broadcasts\BroadcastContext;
use App\Broadcasts\BroadcastContextFactory;
use App\Broadcasts\BroadcastException;
use App\Broadcasts\BroadcastId;
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
use App\Broadcasts\FileKind;
use App\Broadcasts\Podcasts\PodcastEpisode;
use App\Broadcasts\Podcasts\PodcastFeedBuilder;
use App\Broadcasts\Podcasts\PodcastFeedMetadata;
use App\Broadcasts\Podcasts\PodcastGuid;
use App\Broadcasts\Podcasts\PodcastMediaKind;
use App\Broadcasts\Podcasts\PodcastTokenService;
use App\Broadcasts\StashdBroadcast;
use App\Broadcasts\UiControl;
use App\Stashes\StashItemId;
use App\Stashes\StashItemState;
use App\System\State\StateTransitionService;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRecord;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

/**
 * Podcast broadcast plugin — generates RSS podcast feeds with episode media URLs.
 */
#[StashdBroadcast('Podcast', 'RSS podcast feed with episode media URLs.')]
final readonly class PodcastBroadcastPlugin implements \App\Broadcasts\BroadcastPlugin
{
    public function __construct(
        private BroadcastContextFactory $contextFactory,
        private BroadcastPathBuilder $paths,
        private BroadcastItemRepository $broadcastItems,
        private \App\Broadcasts\Podcasts\PodcastAssetSelector $assets,
        private PodcastTokenService $tokens,
        private \App\Broadcasts\Podcasts\PodcastEpisodeUrlBuilder $urls,
        private PodcastGuid $guids,
        private PodcastFeedBuilder $feedBuilder,
        private StateTransitionService $transitions,
        private \App\Broadcasts\Podcasts\PodcastFundingLinkDetector $fundingDetector,
        private \App\Broadcasts\Podcasts\PodcastTranscodeFallback $transcodeFallback,
    ) {
    }

    public function broadcastKeys(): array
    {
        return ['podcast'];
    }

    public function supportedFileKinds(): array
    {
        return [FileKind::Audio, FileKind::Video];
    }

    public function uiControls(): array
    {
        return [
            new UiControl('title', 'Podcast Title', 'text'),
            new UiControl('description', 'Podcast Description', 'text'),
            new UiControl('author', 'Author', 'text'),
            new UiControl('funding_url', 'Funding URL', 'text'),
            new UiControl('media_kind', 'Media Kind', 'select', null, ['audio', 'video']),
        ];
    }

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
        $includedDescriptions = [];
        $failed = [];
        $included = 0;

        foreach ($context->stashItems as $stashItem) {
            if ($stashItem->state !== StashItemState::Active) {
                continue;
            }

            $mediaItem = $context->mediaItems[(string) $stashItem->mediaItemId] ?? null;
            $item = $this->findOrCreateItem($context, $stashItem);

            if ($mediaItem === null) {
                $this->markItemFailed($item, 'podcast_media_item_unavailable');
                $failed[] = (string) $stashItem->id;

                continue;
            }

            $kind = $this->preferredMediaKind($context);
            $selection = $this->selectAsset($context, $stashItem->mediaItemId);

            if ($selection === null) {
                $fallbackCode = $this->transcodeFallback->triggerIfNeeded($stashItem->mediaItemId, $kind);
                $this->markItemFailed($item, $fallbackCode ?? $this->unavailableErrorCode($kind));
                $failed[] = (string) $stashItem->id;

                continue;
            }

            $itemToken = $this->tokens->ensureItemToken($item);
            $this->markItemReady($item);
            $episodes[] = $this->episode($context, $stashItem, $mediaItem, $item, $selection, $broadcastToken, $itemToken);
            $includedDescriptions[] = $mediaItem->description;
            $included++;
        }

        $feedPath = $this->feedPath($context);
        $this->writeFeed($feedPath, $this->feedBuilder->build($this->metadata($context, $broadcastToken, $includedDescriptions), $episodes));

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
                BroadcastId::parse((string) $context->broadcast->id),
                StashItemId::parse((string) $stashItem->id),
            );

            if ($item === null) {
                $stale[] = (string) $stashItem->id;

                continue;
            }

            $kind = $this->preferredMediaKind($context);

            if ($this->selectAsset($context, $stashItem->mediaItemId) === null) {
                $fallbackCode = $this->transcodeFallback->triggerIfNeeded($stashItem->mediaItemId, $kind);
                $this->markItemStale($item, $fallbackCode ?? $this->unavailableErrorCode($kind));
                $stale[] = (string) $item->id;

                continue;
            }

            if ($this->tokens->itemToken($item) === null) {
                $this->markItemStale($item, 'podcast_item_token_unavailable');
                $stale[] = (string) $item->id;

                continue;
            }

            $item->lastVerifiedAt = DateTime::now(Timezone::UTC);
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

    private function findOrCreateItem(BroadcastContext $context, \App\Stashes\StashItemRecord $stashItem): BroadcastItemRecord
    {
        $broadcastId = (string) $context->broadcast->id;
        $item = $this->broadcastItems->findByBroadcastAndStashItem(
            BroadcastId::parse($broadcastId),
            StashItemId::parse((string) $stashItem->id),
        );

        return $item ?? $this->broadcastItems->create(
            broadcastId: BroadcastId::parse($broadcastId),
            stashItemId: StashItemId::parse((string) $stashItem->id),
            mediaItemId: $stashItem->mediaItemId,
        );
    }

    private function markItemReady(BroadcastItemRecord $item): void
    {
        if ($item->state !== BroadcastItemState::Processing) {
            $this->transitions->transitionBroadcastItem($item, BroadcastItemState::Processing);
        }

        $item->publishedPath = null;
        $item->publishedUri = null;
        $item->lastPublishedAt = DateTime::now(Timezone::UTC);
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
        \App\Stashes\StashItemRecord $stashItem,
        MediaItemRecord $mediaItem,
        BroadcastItemRecord $item,
        \App\Broadcasts\Podcasts\PodcastAssetSelection $selection,
        string $broadcastToken,
        string $itemToken,
    ): PodcastEpisode {
        return new PodcastEpisode(
            guid: $this->guids->forItem($item),
            title: $this->episodeTitle($stashItem, $mediaItem),
            description: $this->episodeDescription($stashItem, $mediaItem),
            publishedAt: $mediaItem->publishedAt ?? $stashItem->firstSeenAt ?? $context->broadcast->createdAt ?? DateTime::now(Timezone::UTC),
            enclosureUrl: $this->urls->episodeUrl($broadcastToken, $itemToken, $selection->extension),
            enclosureLength: $selection->length,
            enclosureMimeType: $selection->mimeType,
        );
    }

    /** @param list<string|null> $includedDescriptions */
    private function metadata(BroadcastContext $context, string $broadcastToken, array $includedDescriptions): PodcastFeedMetadata
    {
        $settings = $this->settings($context);
        $title = $this->nonEmptyString($settings['title'] ?? null)
            ?? $context->stash->name
            ?? $context->broadcast->name;
        $description = $this->nonEmptyString($settings['description'] ?? null)
            ?? $context->stash->description
            ?? 'Private Stashd podcast feed.';
        $fundingUrl = $this->nonEmptyString($settings['funding_url'] ?? null)
            ?? $this->fundingDetector->detect($includedDescriptions);

        return new PodcastFeedMetadata(
            title: $title,
            description: $description,
            feedUrl: $this->urls->feedUrl($broadcastToken),
            linkUrl: $this->nonEmptyString($settings['link_url'] ?? null),
            author: $this->nonEmptyString($settings['author'] ?? null),
            imageUrl: $this->nonEmptyString($settings['image_url'] ?? null),
            fundingUrl: $fundingUrl,
        );
    }

    private function episodeTitle(\App\Stashes\StashItemRecord $stashItem, MediaItemRecord $mediaItem): string
    {
        return $this->nonEmptyString($mediaItem->title)
            ?? $this->nonEmptyString($stashItem->displayTitle)
            ?? 'Untitled episode';
    }

    private function episodeDescription(\App\Stashes\StashItemRecord $stashItem, MediaItemRecord $mediaItem): string
    {
        return $this->nonEmptyString($mediaItem->description)
            ?? $this->nonEmptyString($stashItem->displayDescription)
            ?? $this->episodeTitle($stashItem, $mediaItem);
    }

    /** @return array<string, mixed> */
    private function settings(BroadcastContext $context): array
    {
        return $context->broadcast->settings ?? [];
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

    private function preferredMediaKind(BroadcastContext $context): PodcastMediaKind
    {
        return PodcastMediaKind::forBroadcast($context->broadcast);
    }

    private function selectAsset(BroadcastContext $context, MediaItemId $mediaItemId): ?\App\Broadcasts\Podcasts\PodcastAssetSelection
    {
        return match ($this->preferredMediaKind($context)) {
            PodcastMediaKind::Audio => $this->assets->audioAsset($mediaItemId),
            PodcastMediaKind::Video => $this->assets->videoAsset($mediaItemId),
        };
    }

    private function unavailableErrorCode(PodcastMediaKind $kind): string
    {
        return match ($kind) {
            PodcastMediaKind::Audio => 'podcast_audio_asset_unavailable',
            PodcastMediaKind::Video => 'podcast_video_asset_unavailable',
        };
    }
}
