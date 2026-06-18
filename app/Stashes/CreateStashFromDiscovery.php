<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Commands\CommandType;
use App\Providers\ProviderDates;
use App\Providers\StashdUri;
use App\Support\PrefixedUlid;
use App\Vault\MediaItemRepository;
use App\Vault\MediaItemSourceRepository;
use InvalidArgumentException;

use function Tempest\Support\str;

final readonly class CreateStashFromDiscovery
{
    public function __construct(
        private CommandRepository $commands,
        private StashRepository $stashes,
        private StashInputRepository $stashInputs,
        private MediaItemRepository $mediaItems,
        private MediaItemSourceRepository $mediaItemSources,
        private StashItemRepository $stashItems,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function commit(PrefixedUlid $preflightCommandId, array $options = []): StashFromPreflightResult
    {
        $preflight = $this->requireCompletedPreflight($preflightCommandId);
        $preflightResult = $this->decodePreflightResult($preflight);

        $resolved = $preflightResult['resolved_input'] ?? [];
        $discovery = $preflightResult['discovery'] ?? [];
        $discoveredItems = $discovery['discovered_items'] ?? [];

        if (! is_array($discoveredItems) || $discoveredItems === []) {
            throw new InvalidArgumentException('Preflight result is missing discovered items.');
        }

        $name = str((string) ($options['name'] ?? $resolved['title'] ?? 'New Stash'))->trim()->toString();
        $slug = str((string) ($options['slug'] ?? $this->slugify($name)))->trim()->toString();

        if ($this->stashes->findBySlug($slug) !== null) {
            throw new InvalidArgumentException("Stash slug already exists: {$slug}");
        }

        $syncMode = SyncMode::tryFrom((string) ($options['sync_mode'] ?? SyncMode::Automatic->value)) ?? SyncMode::Automatic;
        $downloadPolicy = DownloadPolicy::tryFrom((string) ($options['download_policy'] ?? DownloadPolicy::Video->value)) ?? DownloadPolicy::Video;
        $organizationMode = OrganizationMode::tryFrom((string) ($options['organization_mode'] ?? OrganizationMode::Flat->value)) ?? OrganizationMode::Flat;

        $stash = $this->stashes->create(
            name: $name,
            slug: $slug,
            syncMode: $syncMode,
            downloadPolicy: $downloadPolicy,
            organizationMode: $organizationMode,
            description: is_string($options['description'] ?? null) ? $options['description'] : null,
        );

        $stashId = PrefixedUlid::parse((string) $stash->id);
        $inputType = StashInputTypeMapper::fromProviderInputType((string) ($resolved['input_type'] ?? 'video'));

        $stashInput = $this->stashInputs->create(
            stashId: $stashId,
            providerKey: (string) ($resolved['provider_key'] ?? 'fake'),
            inputType: $inputType,
            sourceUri: (string) ($resolved['source_uri'] ?? $preflightResult['source_uri'] ?? ''),
            providerInputId: (string) ($resolved['provider_input_id'] ?? ''),
            title: is_string($resolved['title'] ?? null) ? $resolved['title'] : null,
            syncMode: $syncMode,
        );

        $stashInputId = PrefixedUlid::parse((string) $stashInput->id);
        $mediaItemsCreated = 0;
        $mediaItemsReused = 0;
        $stashItemsCreated = 0;
        $stashItemsReused = 0;

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

            $existingMedia = $this->mediaItems->findByProviderIdentity(
                (string) ($resolved['provider_key'] ?? 'fake'),
                $providerItemId,
            );

            if ($existingMedia === null) {
                $mediaItem = $this->mediaItems->create(
                    providerKey: (string) ($resolved['provider_key'] ?? 'fake'),
                    providerItemId: $providerItemId,
                    canonicalUri: $canonicalUri,
                    title: $title,
                    description: $description,
                    durationSeconds: isset($item['duration_seconds']) ? (int) $item['duration_seconds'] : null,
                    publishedAt: ProviderDates::tryParse(is_string($item['published_at'] ?? null) ? $item['published_at'] : null),
                    thumbnailUri: is_string($item['thumbnail_uri'] ?? null) && str($item['thumbnail_uri'])->trim()->isNotEmpty()
                        ? StashdUri::parse(str($item['thumbnail_uri'])->trim()->toString())
                        : null,
                );
                $mediaItemsCreated++;
            } else {
                $mediaItem = $existingMedia;
                $mediaItemsReused++;
            }

            $mediaItemId = PrefixedUlid::parse((string) $mediaItem->id);

            if ($this->mediaItemSources->findForMediaItemAndInput($mediaItemId, $stashInputId) === null) {
                $this->mediaItemSources->create(
                    mediaItemId: $mediaItemId,
                    providerKey: (string) ($resolved['provider_key'] ?? 'fake'),
                    providerInputId: (string) ($resolved['provider_input_id'] ?? ''),
                    discoveredUri: $canonicalUri->toString(),
                    stashInputId: $stashInputId,
                    position: $index + 1,
                );
            }

            if ($this->stashItems->findByStashAndMediaItem($stashId, $mediaItemId) === null) {
                $this->stashItems->create(
                    stashId: $stashId,
                    mediaItemId: $mediaItemId,
                    stashInputId: $stashInputId,
                    position: $index + 1,
                );
                $stashItemsCreated++;
            } else {
                $stashItemsReused++;
            }
        }

        return new StashFromPreflightResult(
            stashId: (string) $stash->id,
            stashInputId: (string) $stashInput->id,
            mediaItemsCreated: $mediaItemsCreated,
            mediaItemsReused: $mediaItemsReused,
            stashItemsCreated: $stashItemsCreated,
            stashItemsReused: $stashItemsReused,
            preflightCommandId: (string) $preflight->id,
        );
    }

    private function requireCompletedPreflight(PrefixedUlid $preflightCommandId): CommandRecord
    {
        $command = $this->commands->find($preflightCommandId);

        if ($command === null || $command->type !== CommandType::StashPreflight) {
            throw new InvalidArgumentException('Preflight command not found.');
        }

        if ($command->state !== CommandState::Completed) {
            throw new InvalidArgumentException('Preflight command must be completed before creating a stash.');
        }

        if ($command->resultJson === null) {
            throw new InvalidArgumentException('Preflight command is missing stored results.');
        }

        return $command;
    }

    /** @return array<string, mixed> */
    private function decodePreflightResult(CommandRecord $command): array
    {
        $decoded = json_decode($command->resultJson, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Preflight result JSON is invalid.');
        }

        return $decoded;
    }

    private function slugify(string $name): string
    {
        $slug = str($name)->slug()->toString();

        return $slug !== '' ? $slug : 'stash';
    }
}
