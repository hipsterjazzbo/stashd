<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use Tempest\DateTime\Duration;

test('progressEtaSeconds round-trips through Duration on insert and update', function (): void {
    $jobs = $this->container->get(JobRepository::class);
    $job = $jobs->create(intent: JobIntent::Enrich, entityType: 'test');

    expect($job->progressEtaSeconds)->toBeNull();

    $job->progressEtaSeconds = Duration::seconds(42);
    $jobs->save($job);

    $reloaded = $jobs->find((string) $job->id);
    expect($reloaded?->progressEtaSeconds)->toBeInstanceOf(Duration::class)
        ->and($reloaded?->progressEtaSeconds->getTotalSeconds())->toBe(42.0);

    $reloaded->progressEtaSeconds = Duration::seconds(7);
    $jobs->save($reloaded);

    $reReloaded = $jobs->find((string) $job->id);
    expect($reReloaded?->progressEtaSeconds->getTotalSeconds())->toBe(7.0);
});
