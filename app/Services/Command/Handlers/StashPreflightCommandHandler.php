<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Config\StashdConfig;
use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Stash\PreflightOrigin;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\Command\CommandHandler;
use App\Services\Command\InvalidCommandPayload;

final readonly class StashPreflightCommandHandler implements CommandHandler
{
    public function __construct(
        private JobRepository $jobs,
        private CommandRepository $commands,
        private StashdConfig $config,
    ) {
    }

    public function type(): CommandType
    {
        return CommandType::StashPreflight;
    }

    public function validate(array $options): void
    {
        $sourceUri = trim((string) ($options['sourceUri'] ?? $options['source_uri'] ?? ''));

        if ($sourceUri === '') {
            throw InvalidCommandPayload::withErrors(['source_uri is required.']);
        }
    }

    public function createJobs(CommandRecord $command, array $options): array
    {
        $commandId = PrefixedUlid::parse((string) $command->id);
        $payload = $this->normalizedPayload($command, $options);

        $command->optionsJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        $job = $this->jobs->create(
            intent: JobIntent::Preflight,
            commandId: $commandId,
            entityType: 'preflight',
            entityId: $commandId,
            payload: $payload,
        );

        return [$job];
    }

    public function extras(CommandRecord $command, array $options): array
    {
        $commandId = (string) $command->id;

        return [
            'review_url' => rtrim($this->config->publicUrl, '/') . '/api/v1/stashes/preflight/' . $commandId . '/review',
        ];
    }

    /** @return array<string, mixed> */
    private function normalizedPayload(CommandRecord $command, array $options): array
    {
        $sourceUri = trim((string) ($options['sourceUri'] ?? $options['source_uri'] ?? ''));
        $sourceTitle = $options['sourceTitle'] ?? $options['source_title'] ?? null;
        $sourceTitle = is_string($sourceTitle) && $sourceTitle !== '' ? $sourceTitle : null;
        $originRaw = $options['origin'] ?? null;
        $origin = PreflightOrigin::tryFrom(is_string($originRaw) ? $originRaw : '') ?? PreflightOrigin::Api;

        return [
            'source_uri' => $sourceUri,
            'source_title' => $sourceTitle,
            'origin' => $origin->value,
            'command_id' => (string) $command->id,
        ];
    }
}
