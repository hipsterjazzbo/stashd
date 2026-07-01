<?php

declare(strict_types=1);

namespace App\Transcoding;

use App\Commands\CommandHandler;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandType;
use App\Commands\InvalidCommandPayload;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use App\Support\PrefixedUlid;
use App\Vault\AssetRepository;
use App\Vault\MediaItemRepository;

final readonly class AssetTranscodePodcastAudioCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandRepository $commands,
        private JobRepository $jobs,
        private MediaItemRepository $mediaItems,
        private AssetRepository $assets,
    ) {
    }

    public function type(): CommandType
    {
        return CommandType::AssetTranscodePodcastAudio;
    }

    public function validate(array $options): void
    {
        $payload = $this->normalizedPayload($options);

        if ($payload['media_item_id'] === '' || $payload['source_asset_id'] === '' || $payload['asset_id'] === '') {
            throw InvalidCommandPayload::withErrors(['media_item_id, source_asset_id, and asset_id are required.']);
        }

        if ($this->mediaItems->find($payload['media_item_id']) === null) {
            throw InvalidCommandPayload::withErrors(['Media item not found.']);
        }

        if ($this->assets->find(PrefixedUlid::parse($payload['source_asset_id'])) === null) {
            throw InvalidCommandPayload::withErrors(['Source asset not found.']);
        }

        if ($this->assets->find(PrefixedUlid::parse($payload['asset_id'])) === null) {
            throw InvalidCommandPayload::withErrors(['Target asset not found.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = $this->normalizedPayload($options);
        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $command->targetType = 'asset';
        $command->targetId = $payload['asset_id'];
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::TranscodePodcastAudio,
                commandId: $commandId,
                entityType: 'asset',
                entityId: PrefixedUlid::parse($payload['asset_id']),
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
            'source_asset_id' => trim((string) ($options['sourceAssetId'] ?? $options['source_asset_id'] ?? '')),
            'asset_id' => trim((string) ($options['assetId'] ?? $options['asset_id'] ?? '')),
        ];
    }
}
