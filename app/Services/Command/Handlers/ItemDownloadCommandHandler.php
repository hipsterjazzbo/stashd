<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\MediaItemRepository;
use App\Infrastructure\Persistence\StashItemRepository;
use App\Infrastructure\Persistence\StashRepository;
use App\Services\Command\CommandHandler;
use App\Services\Command\InvalidCommandPayload;

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

        if ($this->mediaItems->find(PrefixedUlid::parse($mediaItemId)) === null) {
            throw InvalidCommandPayload::withErrors(['Media item not found.']);
        }

        if ($this->stashes->find(PrefixedUlid::parse($stashId)) === null) {
            throw InvalidCommandPayload::withErrors(['Stash not found.']);
        }

        if ($this->stashItems->findByStashAndMediaItem(PrefixedUlid::parse($stashId), PrefixedUlid::parse($mediaItemId)) === null) {
            throw InvalidCommandPayload::withErrors(['Media item is not part of the requested stash.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = $this->normalizedPayload($options);
        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
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
