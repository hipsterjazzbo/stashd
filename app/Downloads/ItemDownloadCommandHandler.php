<?php

declare(strict_types=1);

namespace App\Downloads;

use App\Commands\CommandHandler;
use App\Commands\CommandId;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandType;
use App\Commands\InvalidCommandPayload;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use App\Stashes\StashId;
use App\Stashes\StashItemRepository;
use App\Stashes\StashItemState;
use App\Stashes\StashRepository;
use App\Support\PrefixedUlid;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;

final readonly class ItemDownloadCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandRepository $commands,
        private JobRepository $jobs,
        private MediaItemRepository $mediaItems,
        private StashRepository $stashes,
        private StashItemRepository $stashItems,
    ) {
    }

    public function type(): CommandType
    {
        return CommandType::ItemDownload;
    }

    public function validate(array $options): void
    {
        $mediaItemId = trim((string) ($options['mediaItemId'] ?? $options['media_item_id'] ?? ''));
        $stashId = trim((string) ($options['stashId'] ?? $options['stash_id'] ?? ''));

        if ($mediaItemId === '' || $stashId === '') {
            throw InvalidCommandPayload::withErrors(['media_item_id and stash_id are required.']);
        }

        if (! MediaItemId::isValid($mediaItemId) || $this->mediaItems->find(MediaItemId::parse($mediaItemId)) === null) {
            throw InvalidCommandPayload::withErrors(['Media item not found.']);
        }

        if (! StashId::isValid($stashId) || $this->stashes->find(StashId::parse($stashId)) === null) {
            throw InvalidCommandPayload::withErrors(['Stash not found.']);
        }

        $stashItem = $this->stashItems->findByStashAndMediaItem(StashId::parse($stashId), MediaItemId::parse($mediaItemId));

        if ($stashItem === null) {
            throw InvalidCommandPayload::withErrors(['Media item is not part of the requested stash.']);
        }

        // The item was already excluded at commit time (title-regex filter,
        // include_shorts/include_live content-type filter, etc. -- see
        // CreateStashFromDiscovery::ignoredReason()) -- an ignored item is
        // never downloadable through any path, dispatched explicitly or not,
        // regardless of why it was ignored. Without this, item.download can
        // be dispatched directly against an ignored item and creates a job
        // that's doomed to fail (e.g. yt-dlp refusing an unaired premiere)
        // instead of failing fast with the reason the app already knows.
        if ($stashItem->state === StashItemState::Ignored) {
            $reason = $stashItem->ignoredReason ?? 'unknown reason';
            throw InvalidCommandPayload::withErrors(["This item is ignored ({$reason}) and cannot be downloaded."]);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = CommandId::fromPrimaryKey($command->id);
        $payload = $this->normalizedPayload($options);
        $command->options = $payload;
        $command->targetType = 'media_item';
        $command->targetId = $payload['media_item_id'];
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::Download,
                commandId: $commandId,
                entityType: 'media_item',
                entityId: PrefixedUlid::parse($payload['media_item_id']),
                priority: 50,
                payload: $payload,
            ),
        ];
    }

    public function extras(CommandRecord $command, array $options): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    private function normalizedPayload(array $options): array
    {
        return [
            'media_item_id' => trim((string) ($options['mediaItemId'] ?? $options['media_item_id'] ?? '')),
            'stash_id' => trim((string) ($options['stashId'] ?? $options['stash_id'] ?? '')),
            'force' => (bool) ($options['force'] ?? false),
        ];
    }
}
