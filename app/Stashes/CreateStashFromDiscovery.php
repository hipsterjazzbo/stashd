<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Commands\CommandDispatchService;
use App\Commands\CommandId;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Commands\CommandType;
use App\Downloads\DownloadPolicyEvaluator;
use App\Jobs\JobIntent;
use App\Providers\InputOption;
use App\Providers\InputOptionType;
use App\Providers\ProviderDates;
use App\Providers\StashdUri;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;
use App\Vault\MediaItemSourceRepository;
use InvalidArgumentException;

use function Tempest\Support\str;

/**
 * Commits a completed preflight into a stash input on an existing stash.
 *
 * Preflight only resolves identity and takes a cheap sample; this class re-runs
 * full discovery (the best available strategy, e.g. the YouTube Data API once
 * keyed) rather than replaying preflight's frozen sample, then persists the
 * stash input, media items, sources, and stash items, deduplicating against
 * whatever the stash (and the wider Vault) already has.
 */
final readonly class CreateStashFromDiscovery
{
    public function __construct(
        private CommandRepository $commands,
        private StashRepository $stashes,
        private StashInputRepository $stashInputs,
        private MediaItemRepository $mediaItems,
        private MediaItemSourceRepository $mediaItemSources,
        private StashItemRepository $stashItems,
        private CommandDispatchService $commandDispatch,
        private DownloadPolicyEvaluator $downloadPolicy,
        private DiscoverStashInput $discovery,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function commitInput(StashRecord $stash, CommandRecord $preflightCommand, array $options = []): StashInputCommitResult
    {
        $preflight = $this->requireCompletedPreflight($preflightCommand);
        $preflightResult = $this->decodePreflightResult($preflight);

        $sourceUri = str((string) ($preflightResult['source_uri'] ?? ''))->trim()->toString();
        $sourceTitle = is_string($preflightResult['source_title'] ?? null) ? $preflightResult['source_title'] : null;
        $origin = is_string($preflightResult['origin'] ?? null) ? $preflightResult['origin'] : PreflightOrigin::Api->value;

        if ($sourceUri === '') {
            throw new InvalidArgumentException('Preflight result is missing its source_uri.');
        }

        $discovered = $this->discovery->execute([
            'source_uri' => $sourceUri,
            'source_title' => $sourceTitle,
            'origin' => $origin,
        ], JobIntent::InitialBackfill);

        $resolved = $discovered->resolvedInput;
        $discoveredItems = $discovered->discoveredItems;
        $inputOptions = StashInputOptions::fromArray($options);
        $excludedContentTypes = $this->excludedContentTypes($discovered->inputOptions, $inputOptions);

        $stashId = StashId::parse((string) $stash->id);
        $inputType = StashInputTypeMapper::fromProviderInputType($resolved->inputType);

        $syncMode = SyncMode::tryFrom((string) ($options['sync_mode'] ?? SyncMode::Automatic->value)) ?? SyncMode::Automatic;

        $isFirstInput = $this->stashInputs->listForStash($stashId) === [];

        $stashInput = $this->stashInputs->create(
            stashId: $stashId,
            providerKey: $resolved->providerKey,
            inputType: $inputType,
            sourceUri: $resolved->sourceUri->toString(),
            providerInputId: $resolved->providerInputId,
            title: $resolved->title,
            syncMode: $syncMode,
            options: $inputOptions,
        );

        if ($stash->iconUri === null && $resolved->sourceAvatarUri !== null) {
            $this->stashes->update($stash, iconUri: $resolved->sourceAvatarUri->toString());
        }

        // Only the stash's first input defaults its name -- a stash created via
        // the "+ New stash" modal with a link but no typed title gets 'New Stash'
        // from StashController::create() (no channel info exists at that point,
        // before any input is resolved); this backfills it once that's known,
        // the same way iconUri already does above. Later inputs on an
        // already-named (possibly multi-source) stash must never overwrite it.
        if ($isFirstInput && $stash->name === 'New Stash' && $resolved->sourceTitle !== null) {
            $this->stashes->update($stash, name: $resolved->sourceTitle);
        }

        $stashInputId = StashInputId::parse((string) $stashInput->id);
        $mediaItemsCreated = 0;
        $mediaItemsReused = 0;
        $stashItemsCreated = 0;
        $stashItemsReused = 0;

        /** @var list<string> $downloadableMediaItemIds */
        $downloadableMediaItemIds = [];

        foreach (array_values($discoveredItems) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $providerItemId = str((string) ($item['provider_item_id'] ?? ''))->trim()->toString();
            $canonicalUriRaw = str((string) ($item['canonical_uri'] ?? ''))->trim()->toString();
            $title = str((string) ($item['title'] ?? 'Untitled'))->trim()->toString();
            $description = is_string($item['description'] ?? null) && str($item['description'])->trim()->isNotEmpty()
                ? str($item['description'])->trim()->toString()
                : null;

            if ($providerItemId === '' || $canonicalUriRaw === '') {
                continue;
            }

            $canonicalUri = StashdUri::parse($canonicalUriRaw);

            $existingMedia = $this->mediaItems->findByProviderIdentity($resolved->providerKey, $providerItemId);

            if ($existingMedia === null) {
                $mediaItem = $this->mediaItems->create(
                    providerKey: $resolved->providerKey,
                    providerItemId: $providerItemId,
                    canonicalUri: $canonicalUri,
                    title: $title,
                    description: $description,
                    durationSeconds: isset($item['duration_seconds']) ? (int) $item['duration_seconds'] : null,
                    publishedAt: ProviderDates::tryParse(is_string($item['published_at'] ?? null) ? $item['published_at'] : null),
                    thumbnailUri: is_string($item['thumbnail_uri'] ?? null) && str($item['thumbnail_uri'])->trim()->isNotEmpty()
                        ? StashdUri::parse(str($item['thumbnail_uri'])->trim()->toString())
                        : null,
                    contentType: is_string($item['content_type'] ?? null) ? $item['content_type'] : null,
                );
                $mediaItemsCreated++;
            } else {
                $mediaItem = $existingMedia;
                $mediaItemsReused++;
            }

            $mediaItemId = MediaItemId::parse((string) $mediaItem->id);

            if ($this->mediaItemSources->findForMediaItemAndInput($mediaItemId, $stashInputId) === null) {
                $this->mediaItemSources->create(
                    mediaItemId: $mediaItemId,
                    providerKey: $resolved->providerKey,
                    providerInputId: $resolved->providerInputId,
                    discoveredUri: $canonicalUri->toString(),
                    stashInputId: $stashInputId,
                    position: $index + 1,
                );
            }

            if ($this->stashItems->findByStashAndMediaItem($stashId, $mediaItemId) === null) {
                $contentType = is_string($item['content_type'] ?? null) ? $item['content_type'] : null;
                $ignoredReason = $this->ignoredReason($title, $contentType, $inputOptions, $excludedContentTypes);

                $stashItem = $this->stashItems->create(
                    stashId: $stashId,
                    mediaItemId: $mediaItemId,
                    stashInputId: $stashInputId,
                    position: $index + 1,
                    ignoredReason: $ignoredReason,
                    state: $ignoredReason !== null ? StashItemState::Ignored : StashItemState::Active,
                );
                $stashItemsCreated++;

                if ($stashItem->state !== StashItemState::Ignored) {
                    $downloadableMediaItemIds[] = $mediaItemId->toString();
                }
            } else {
                $stashItemsReused++;
            }
        }

        if ($this->downloadPolicy->allowsAutomaticDownload($stash->downloadPolicy)) {
            foreach ($downloadableMediaItemIds as $downloadableMediaItemId) {
                $this->commandDispatch->dispatch(CommandType::ItemDownload, [
                    'mediaItemId' => $downloadableMediaItemId,
                    'stashId' => $stashId->toString(),
                ]);
            }
        }

        return new StashInputCommitResult(
            stashId: (string) $stash->id,
            stashInputId: (string) $stashInput->id,
            mediaItemsCreated: $mediaItemsCreated,
            mediaItemsReused: $mediaItemsReused,
            stashItemsCreated: $stashItemsCreated,
            stashItemsReused: $stashItemsReused,
            preflightCommandId: (string) $preflight->id,
        );
    }

    /**
     * @param list<InputOption> $declaredOptions
     *
     * @return list<string>
     */
    private function excludedContentTypes(array $declaredOptions, ?StashInputOptions $inputOptions): array
    {
        $excluded = [];

        foreach ($declaredOptions as $option) {
            if ($option->type !== InputOptionType::Bool || $option->excludesContentTypes === []) {
                continue;
            }

            $effectiveValue = $inputOptions?->providerValue($option) ?? $option->default;

            if ($effectiveValue === false) {
                array_push($excluded, ...$option->excludesContentTypes);
            }
        }

        return $excluded;
    }

    /** @param list<string> $excludedContentTypes */
    private function ignoredReason(
        string $title,
        ?string $contentType,
        ?StashInputOptions $inputOptions,
        array $excludedContentTypes,
    ): ?string {
        if ($inputOptions !== null) {
            if ($inputOptions->titleRegexInclude !== null
                && StashInputOptions::matches($inputOptions->titleRegexInclude, $title) === false) {
                return 'filter_title_regex';
            }

            if ($inputOptions->titleRegexExclude !== null
                && StashInputOptions::matches($inputOptions->titleRegexExclude, $title) === true) {
                return 'filter_title_regex';
            }
        }

        if ($contentType !== null && in_array($contentType, $excludedContentTypes, true)) {
            return 'filter_video_type';
        }

        return null;
    }

    private function requireCompletedPreflight(CommandRecord $preflightCommand): CommandRecord
    {
        $command = $this->commands->find(CommandId::parse((string) $preflightCommand->id));

        if ($command === null || $command->type !== CommandType::StashPreflight) {
            throw new InvalidArgumentException('Preflight command not found.');
        }

        if ($command->state !== CommandState::Completed) {
            throw new InvalidArgumentException('Preflight command must be completed before adding an input.');
        }

        if ($command->result === null) {
            throw new InvalidArgumentException('Preflight command is missing stored results.');
        }

        return $command;
    }

    /** @return array<string, mixed> */
    private function decodePreflightResult(CommandRecord $command): array
    {
        return $command->result ?? throw new InvalidArgumentException('Preflight result is missing.');
    }
}
