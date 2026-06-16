<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\BroadcastRepository;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\Command\CommandHandler;
use App\Services\Command\InvalidCommandPayload;

abstract readonly class AbstractBroadcastCommandHandler implements CommandHandler
{
    public function __construct(
        protected CommandRepository $commands,
        protected JobRepository $jobs,
        protected BroadcastRepository $broadcasts,
    ) {
    }

    abstract protected function action(): string;

    public function validate(array $options): void
    {
        $broadcastId = $this->broadcastIdFromOptions($options);

        if ($this->broadcasts->find(PrefixedUlid::parse($broadcastId)) === null) {
            throw InvalidCommandPayload::withErrors(['Broadcast not found.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = $this->normalizedPayload($options);
        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $command->targetType = 'broadcast';
        $command->targetId = $payload['broadcast_id'];
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::Broadcast,
                commandId: $commandId,
                entityType: 'broadcast',
                entityId: PrefixedUlid::parse($payload['broadcast_id']),
                priority: 40,
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
            'broadcast_id' => $this->broadcastIdFromOptions($options),
            'action' => $this->action(),
        ];
    }

    protected function broadcastIdFromOptions(array $options): string
    {
        $broadcastId = trim((string) ($options['broadcastId'] ?? $options['broadcast_id'] ?? ''));

        if ($broadcastId === '') {
            throw InvalidCommandPayload::withErrors(['broadcast_id is required.']);
        }

        return $broadcastId;
    }
}
