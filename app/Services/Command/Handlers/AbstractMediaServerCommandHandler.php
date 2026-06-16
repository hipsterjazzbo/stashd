<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\MediaServerConnectionRepository;
use App\Services\Command\CommandHandler;
use App\Services\Command\InvalidCommandPayload;

abstract readonly class AbstractMediaServerCommandHandler implements CommandHandler
{
    public function __construct(
        protected CommandRepository $commands,
        protected JobRepository $jobs,
        protected MediaServerConnectionRepository $connections,
    ) {
    }

    abstract protected function action(): string;

    public function validate(array $options): void
    {
        $connectionId = $this->connectionIdFromOptions($options);

        if ($this->connections->find(PrefixedUlid::parse($connectionId)) === null) {
            throw InvalidCommandPayload::withErrors(['Media server connection not found.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = $this->normalizedPayload($options);
        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $command->targetType = 'media_server_connection';
        $command->targetId = $payload['media_server_connection_id'];
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::MediaServer,
                commandId: $commandId,
                entityType: 'media_server_connection',
                entityId: PrefixedUlid::parse($payload['media_server_connection_id']),
                priority: 45,
                payload: $payload,
            ),
        ];
    }

    public function extras(CommandRecord $command, array $options): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function normalizedPayload(array $options): array
    {
        return [
            'media_server_connection_id' => $this->connectionIdFromOptions($options),
            'action' => $this->action(),
        ];
    }

    protected function connectionIdFromOptions(array $options): string
    {
        $id = trim((string) (
            $options['mediaServerConnectionId']
            ?? $options['media_server_connection_id']
            ?? $options['connectionId']
            ?? $options['connection_id']
            ?? ''
        ));

        if ($id === '') {
            throw InvalidCommandPayload::withErrors(['media_server_connection_id is required.']);
        }

        return $id;
    }
}
