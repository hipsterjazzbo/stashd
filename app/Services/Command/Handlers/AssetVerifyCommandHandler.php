<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\AssetRepository;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\Command\CommandHandler;
use App\Services\Command\InvalidCommandPayload;

final readonly class AssetVerifyCommandHandler implements CommandHandler
{
    public function __construct(
        private AssetRepository $assets,
        private CommandRepository $commands,
        private JobRepository $jobs,
    ) {
    }

    public function type(): CommandType
    {
        return CommandType::AssetVerify;
    }

    public function validate(array $options): void
    {
        $assetId = trim((string) ($options['assetId'] ?? $options['asset_id'] ?? ''));

        if ($assetId === '') {
            throw InvalidCommandPayload::withErrors(['asset_id is required.']);
        }

        if ($this->assets->find(PrefixedUlid::parse($assetId)) === null) {
            throw InvalidCommandPayload::withErrors(['Asset not found.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = [
            'asset_id' => trim((string) ($options['assetId'] ?? $options['asset_id'] ?? '')),
        ];
        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $command->targetType = 'asset';
        $command->targetId = $payload['asset_id'];
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::VerifyVault,
                commandId: $commandId,
                entityType: 'asset',
                entityId: PrefixedUlid::parse($payload['asset_id']),
                payload: $payload,
            ),
        ];
    }

    public function extras(CommandRecord $command, array $options): array
    {
        return [];
    }
}
