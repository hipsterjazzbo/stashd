<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Commands\CommandHandler;
use App\Commands\CommandId;
use App\Commands\CommandRecord;
use App\Commands\CommandType;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use App\Support\PrefixedUlid;

final readonly class SponsorBlockRefreshCommandHandler implements CommandHandler
{
    public function __construct(private JobRepository $jobs)
    {
    }

    public function type(): CommandType
    {
        return CommandType::SystemSponsorBlockRefresh;
    }

    /** @param array<string, mixed> $options */
    public function validate(array $options): void
    {
    }

    /** @param array<string, mixed> $options */
    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = CommandId::fromPrimaryKey($command->id);

        return [$this->jobs->create(
            intent: JobIntent::SponsorBlockRefresh,
            commandId: $commandId,
            entityType: 'system',
            entityId: PrefixedUlid::parse($commandId->toString()),
        )];
    }

    /** @param array<string, mixed> $options */
    public function extras(CommandRecord $command, array $options): array
    {
        return [];
    }
}
