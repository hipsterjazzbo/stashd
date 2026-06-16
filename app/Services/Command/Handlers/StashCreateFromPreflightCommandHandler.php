<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandState;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\StashRepository;
use App\Services\Command\CommandHandler;
use App\Services\Command\InvalidCommandPayload;

final readonly class StashCreateFromPreflightCommandHandler implements CommandHandler
{
    public function __construct(
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StashRepository $stashes,
    ) {
    }

    public function type(): CommandType
    {
        return CommandType::StashCreateFromPreflight;
    }

    public function validate(array $options): void
    {
        $preflightCommandId = trim((string) ($options['preflightCommandId'] ?? $options['preflight_command_id'] ?? ''));

        if ($preflightCommandId === '') {
            throw InvalidCommandPayload::withErrors(['preflight_command_id is required.']);
        }

        $command = $this->commands->find(PrefixedUlid::parse($preflightCommandId));

        if ($command === null || $command->type !== CommandType::StashPreflight) {
            throw InvalidCommandPayload::withErrors(['Preflight command not found.']);
        }

        if ($command->state !== CommandState::Completed || $command->resultJson === null) {
            throw InvalidCommandPayload::withErrors(['Preflight command must be completed with stored results.']);
        }

        $result = json_decode($command->resultJson, true);

        if (! is_array($result) || ! is_array($result['discovery']['discovered_items'] ?? null) || $result['discovery']['discovered_items'] === []) {
            throw InvalidCommandPayload::withErrors(['Preflight result is missing discovered items.']);
        }

        $slug = trim((string) ($options['slug'] ?? ''));

        if ($slug !== '' && $this->stashes->findBySlug($slug) !== null) {
            throw InvalidCommandPayload::withErrors(["Stash slug already exists: {$slug}"]);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $preflightCommandId = trim((string) ($options['preflightCommandId'] ?? $options['preflight_command_id'] ?? ''));
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = [
            'preflight_command_id' => $preflightCommandId,
            ...$this->normalizedStashOptions($options),
        ];

        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        return [
            $this->jobs->create(
                intent: JobIntent::CreateFromPreflight,
                commandId: $commandId,
                entityType: 'stash',
                entityId: $commandId,
                payload: $payload,
            ),
        ];
    }

    public function extras(CommandRecord $command, array $options): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    private function normalizedStashOptions(array $options): array
    {
        $normalized = [];

        foreach (['name', 'slug', 'description', 'sync_mode', 'download_policy', 'organization_mode'] as $key) {
            $camel = lcfirst(str_replace('_', '', ucwords($key, '_')));
            $value = $options[$camel] ?? $options[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
