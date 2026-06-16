<?php

declare(strict_types=1);

namespace App\Services\Command;

use App\Domain\Auth\UserRecord;
use App\Domain\Command\CommandType;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Services\Activity\ActivityEventService;
use App\Services\Event\EventPublisher;

final readonly class CommandDispatchService
{
    public function __construct(
        private CommandRepository $commands,
        private CommandHandlerRegistry $handlers,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    /** @param array<string, mixed> $options */
    public function dispatch(CommandType $type, array $options, ?UserRecord $user = null): CommandDispatchResult
    {
        $handler = $this->handlers->handlerFor($type);
        $handler->validate($options);

        $command = $this->commands->create(
            type: $type,
            targetType: $handler->type()->value,
            options: $options,
            createdByUserId: $user === null ? null : PrefixedUlid::parse((string) $user->id),
        );

        $this->activity->commandAccepted($command);

        $jobs = $handler->createJobs($command, $options);

        foreach ($jobs as $job) {
            $this->publisher->jobCreated($job);
        }

        return new CommandDispatchResult(
            command: $command,
            jobs: $jobs,
            extras: $handler->extras($command, $options),
        );
    }
}
