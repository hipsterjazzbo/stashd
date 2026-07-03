<?php

declare(strict_types=1);

namespace App\MediaServers;

use App\Commands\CommandHandler;
use App\Commands\CommandId;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandType;
use App\Commands\InvalidCommandPayload;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use App\Support\PrefixedUlid;

final readonly class MediaServerCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandRepository $commands,
        private JobRepository $jobs,
        private MediaServerConnectionRepository $connections,
        private CommandType $commandType,
    ) {
    }

    public function type(): CommandType
    {
        return $this->commandType;
    }

    public function validate(array $options): void
    {
        $connectionId = $this->connectionIdFromOptions($options);

        if ($this->connections->find(PrefixedUlid::parse($connectionId)) === null) {
            throw InvalidCommandPayload::withErrors(['Media server connection not found.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = CommandId::fromPrimaryKey($command->id);
        $payload = $this->normalizedPayload($options);
        $command->options = $payload;
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
    private function normalizedPayload(array $options): array
    {
        return [
            'media_server_connection_id' => $this->connectionIdFromOptions($options),
            'action' => $this->actionForType($this->commandType),
        ];
    }

    private function connectionIdFromOptions(array $options): string
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

    private function actionForType(CommandType $type): string
    {
        return match ($type) {
            CommandType::MediaServerTestConnection => 'test_connection',
            CommandType::MediaServerListLibraries => 'list_libraries',
            default => throw InvalidCommandPayload::withErrors(['Unsupported media server command type.']),
        };
    }
}
