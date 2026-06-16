<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Command\CommandState;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Job\JobState;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\State\InvalidStateTransition;
use App\Services\State\StateTransitionService;

test('state transition service persists valid command transitions', function (): void {
    $commands = $this->container->get(CommandRepository::class);
    $transitions = $this->container->get(StateTransitionService::class);

    $command = $commands->create(CommandType::StashPreflight);
    expect($command->state)->toBe(CommandState::Accepted);

    $transitions->transitionCommand($command, CommandState::Running);
    expect($command->state)->toBe(CommandState::Running);

    $transitions->transitionCommand($command, CommandState::Completed);
    expect($command->state)->toBe(CommandState::Completed);
});

test('state transition service rejects invalid job transitions', function (): void {
    $jobs = $this->container->get(JobRepository::class);
    $transitions = $this->container->get(StateTransitionService::class);

    $job = $jobs->create(intent: JobIntent::Preflight);
    expect($job->state)->toBe(JobState::Pending);

    try {
        $transitions->transitionJob($job, JobState::Ready);
        test()->fail('Expected InvalidStateTransition was not thrown.');
    } catch (InvalidStateTransition $exception) {
        expect($exception->getMessage())->toContain('Job cannot transition from pending to ready');
    }
});

test('job repository claims pending jobs via state transition service', function (): void {
    $jobs = $this->container->get(JobRepository::class);
    $transitions = $this->container->get(StateTransitionService::class);

    $jobs->create(intent: JobIntent::Preflight, priority: 10);
    $jobs->create(intent: JobIntent::Preflight, priority: 5);

    $claimed = $jobs->claimNextPending($transitions);

    expect($claimed)->not->toBeNull()
        ->and($claimed->state)->toBe(JobState::Processing);
});
