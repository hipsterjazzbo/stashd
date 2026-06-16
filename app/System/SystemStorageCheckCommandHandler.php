<?php

declare(strict_types=1);

namespace App\System;

use App\Commands\CommandHandler;
use App\Commands\CommandRecord;
use App\Commands\CommandType;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use App\Support\PrefixedUlid;

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
