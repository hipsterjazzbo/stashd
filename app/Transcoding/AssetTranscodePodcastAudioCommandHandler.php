<?php

declare(strict_types=1);

namespace App\Transcoding;

use App\Commands\CommandHandler;
use App\Commands\CommandId;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandType;
use App\Commands\InvalidCommandPayload;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use App\Support\PrefixedUlid;
use App\Vault\AssetId;
use App\Vault\AssetRepository;
use App\Vault\MediaItemId;
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

        if (! MediaItemId::isValid($payload['media_item_id']) || $this->mediaItems->find(MediaItemId::parse($payload['media_item_id'])) === null) {
            throw InvalidCommandPayload::withErrors(['Media item not found.']);
        }

        if (! AssetId::isValid($payload['source_asset_id']) || $this->assets->find(AssetId::parse($payload['source_asset_id'])) === null) {
            throw InvalidCommandPayload::withErrors(['Source asset not found.']);
        }

        if (! AssetId::isValid($payload['asset_id']) || $this->assets->find(AssetId::parse($payload['asset_id'])) === null) {
            throw InvalidCommandPayload::withErrors(['Target asset not found.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = CommandId::parse((string) $command->id);
        $payload = $this->normalizedPayload($options);
        $command->options = $payload;
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

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    private function normalizedPayload(array $options): array
    {
        $string = static fn (mixed $value): string => is_string($value) ? trim($value) : '';

        return [
            'media_item_id' => $string($options['mediaItemId'] ?? $options['media_item_id'] ?? null),
            'source_asset_id' => $string($options['sourceAssetId'] ?? $options['source_asset_id'] ?? null),
            'asset_id' => $string($options['assetId'] ?? $options['asset_id'] ?? null),
        ];
    }
}
