<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\Command\CommandHandler;

final readonly class SystemVerifyVaultCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandRepository $commands,
        private JobRepository $jobs,
    ) {
    }

    public function type(): CommandType
    {
        return CommandType::SystemVerifyVault;
    }

    public function validate(array $options): void
    {
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = ['scope' => 'vault'];
        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::VerifyVault,
                commandId: $commandId,
                entityType: 'vault',
                entityId: $commandId,
                payload: $payload,
            ),
        ];
    }

    public function extras(CommandRecord $command, array $options): array
    {
        return [];
    }
}
