<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\Command\CommandHandler;

final readonly class SystemStorageCheckCommandHandler implements CommandHandler
{
    public function __construct(
        private JobRepository $jobs,
    ) {
    }

    public function type(): CommandType
    {
        return CommandType::SystemStorageCheck;
    }

    public function validate(array $options): void
    {
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);

        return [
            $this->jobs->create(
                intent: JobIntent::StorageCheck,
                commandId: $commandId,
                entityType: 'system',
                entityId: $commandId,
                payload: $options === [] ? null : $options,
            ),
        ];
    }

    public function extras(CommandRecord $command, array $options): array
    {
        return [];
    }
}
