<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Commands\CommandId;
use App\Commands\CommandRepository;
use App\Commands\CommandType;
use App\Jobs\JobId;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;

test('JobRecord::commandId round-trips as CommandId through insert, where-lookup, and reload', function (): void {
    $commands = $this->container->get(CommandRepository::class);
    $jobs = $this->container->get(JobRepository::class);

    $command = $commands->create(type: CommandType::SystemVerifyVault);
    $commandId = CommandId::parse((string) $command->id);

    $created = $jobs->create(intent: JobIntent::VerifyVault, commandId: $commandId, entityType: 'test');

    expect($created->commandId)->toBeInstanceOf(CommandId::class)
        ->and($created->commandId->toString())->toBe($commandId->toString());

    // WHERE-clause lookup: the property caster only fixes hydration, not raw
    // bound params, so this proves listForCommand's explicit ->toString() works.
    $forCommand = $jobs->listForCommand($commandId);
    expect($forCommand)->toHaveCount(1)
        ->and($forCommand[0]->commandId?->toString())->toBe($commandId->toString());

    $reloaded = $jobs->find(JobId::parse((string) $created->id));
    expect($reloaded?->commandId)->toBeInstanceOf(CommandId::class)
        ->and($reloaded?->commandId->toString())->toBe($commandId->toString());
});
