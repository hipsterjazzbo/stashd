<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Commands\CommandHandler;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandType;
use App\Commands\InvalidCommandPayload;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use App\Support\PrefixedUlid;

final readonly class BroadcastCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandRepository $commands,
        private JobRepository $jobs,
        private BroadcastRepository $broadcasts,
        private CommandType $commandType,
    ) {
    }

    public function type(): CommandType
    {
        return $this->commandType;
    }

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
    private function normalizedPayload(array $options): array
    {
        return [
            'broadcast_id' => $this->broadcastIdFromOptions($options),
            'action' => $this->actionForType($this->commandType),
        ];
    }

    private function broadcastIdFromOptions(array $options): string
    {
        $broadcastId = trim((string) ($options['broadcastId'] ?? $options['broadcast_id'] ?? ''));

        if ($broadcastId === '') {
            throw InvalidCommandPayload::withErrors(['broadcast_id is required.']);
        }

        return $broadcastId;
    }

    private function actionForType(CommandType $type): string
    {
        return match ($type) {
            CommandType::BroadcastPlan => 'plan',
            CommandType::BroadcastRebuild => 'rebuild',
            CommandType::BroadcastVerify => 'verify',
            CommandType::BroadcastPrune => 'prune',
            CommandType::BroadcastTrigger => 'trigger',
            default => throw InvalidCommandPayload::withErrors(['Unsupported broadcast command type.']),
        };
    }
}
