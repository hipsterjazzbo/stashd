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
        private BroadcastItemRepository $broadcastItems,
        private CommandType $commandType,
    ) {
    }

    public function type(): CommandType
    {
        return $this->commandType;
    }

    public function validate(array $options): void
    {
        if ($this->commandType === CommandType::BroadcastRebuildItem) {
            $item = $this->broadcastItemFromOptions($options);

            if ($item === null) {
                throw InvalidCommandPayload::withErrors(['Broadcast item not found.']);
            }

            return;
        }

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
        $command->targetType = $this->commandType === CommandType::BroadcastRebuildItem ? 'broadcast_item' : 'broadcast';
        $command->targetId = $payload['broadcast_item_id'] ?? $payload['broadcast_id'];
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::Broadcast,
                commandId: $commandId,
                entityType: $command->targetType,
                entityId: PrefixedUlid::parse($command->targetId),
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
        if ($this->commandType === CommandType::BroadcastRebuildItem) {
            $item = $this->broadcastItemFromOptions($options)
                ?? throw InvalidCommandPayload::withErrors(['Broadcast item not found.']);

            return [
                'broadcast_id' => (string) $item->broadcastId,
                'broadcast_item_id' => (string) $item->id,
                'action' => 'rebuild_item',
            ];
        }

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
            CommandType::BroadcastRebuildItem => 'rebuild_item',
            CommandType::BroadcastVerify => 'verify',
            CommandType::BroadcastPrune => 'prune',
            CommandType::BroadcastTrigger => 'trigger',
            CommandType::BroadcastRotateToken => 'rotate_token',
            default => throw InvalidCommandPayload::withErrors(['Unsupported broadcast command type.']),
        };
    }

    private function broadcastItemFromOptions(array $options): ?BroadcastItemRecord
    {
        $id = trim((string) ($options['broadcastItemId'] ?? $options['broadcast_item_id'] ?? ''));

        return BroadcastItemId::isValid($id) ? $this->broadcastItems->find(BroadcastItemId::parse($id)) : null;
    }
}
