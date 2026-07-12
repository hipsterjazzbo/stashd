<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Commands\CommandHandler;
use App\Commands\CommandId;
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
        $broadcast = BroadcastId::isValid($broadcastId) ? $this->broadcasts->find(BroadcastId::parse($broadcastId)) : null;

        if ($broadcast === null) {
            throw InvalidCommandPayload::withErrors(['Broadcast not found.']);
        }

        if (
            $this->commandType === CommandType::BroadcastRotateToken
            && $broadcast->type !== 'podcast'
        ) {
            throw InvalidCommandPayload::withErrors(['Token rotation is only supported for podcast broadcasts.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = CommandId::fromPrimaryKey($command->id);
        $payload = $this->normalizedPayload($options);
        $command->options = $payload;
        $command->targetType = 'broadcast';
        $command->targetId = $payload['broadcast_id'];
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::Broadcast,
                commandId: $commandId,
                entityType: 'broadcast',
                entityId: PrefixedUlid::parse($payload['broadcast_id']),
                priority: 60,
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
            CommandType::BroadcastRotateToken => 'rotate_token',
            default => throw InvalidCommandPayload::withErrors(['Unsupported broadcast command type.']),
        };
    }
}
