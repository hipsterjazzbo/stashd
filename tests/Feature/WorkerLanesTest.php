<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\JobIntent;
use App\Jobs\JobLane;
use App\Jobs\JobRecord;
use App\Jobs\JobRepository;
use App\Jobs\JobState;
use App\Jobs\JobWorkerService;
use App\Jobs\WorkerProcessProbe;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

test('a lane-scoped claim only takes jobs from its own lane', function (): void {
    $jobs = $this->container->get(JobRepository::class);

    $preflight = $jobs->create(intent: JobIntent::Preflight, entityType: 'test');
    $download = $jobs->create(intent: JobIntent::Download, entityType: 'test');
    $backfill = $jobs->create(intent: JobIntent::InitialBackfill, entityType: 'test');

    $claimedBulk = $jobs->claimNextPending(JobLane::Bulk);
    expect((string) $claimedBulk->id)->toBe((string) $download->id);

    $claimedInteractive = $jobs->claimNextPending(JobLane::Interactive);
    expect((string) $claimedInteractive->id)->toBe((string) $preflight->id);

    $claimedDiscovery = $jobs->claimNextPending(JobLane::Discovery);
    expect((string) $claimedDiscovery->id)->toBe((string) $backfill->id);

    // Every lane's queue is now empty.
    expect($jobs->claimNextPending(JobLane::Bulk))->toBeNull()
        ->and($jobs->claimNextPending())->toBeNull();
});

test('an interactive job is claimable while a bulk job is processing', function (): void {
    $jobs = $this->container->get(JobRepository::class);

    $jobs->create(intent: JobIntent::Download, entityType: 'test');
    $preflight = $jobs->create(intent: JobIntent::Preflight, entityType: 'test');

    // The bulk lane takes the download (and would hold it for minutes).
    expect($jobs->claimNextPending(JobLane::Bulk))->not->toBeNull();

    // The interactive lane is not blocked behind it.
    $claimed = $jobs->claimNextPending(JobLane::Interactive);
    expect((string) $claimed->id)->toBe((string) $preflight->id)
        ->and($claimed->state)->toBe(JobState::Processing);
});

test('a claim records its owner token and loses gracefully when the job was already taken', function (): void {
    $jobs = $this->container->get(JobRepository::class);
    $probe = $this->container->get(WorkerProcessProbe::class);

    $job = $jobs->create(intent: JobIntent::Preflight, entityType: 'test');

    $claimed = $jobs->claimNextPending(null, $probe->currentToken());
    expect((string) $claimed->id)->toBe((string) $job->id)
        ->and($claimed->ownerToken)->toBe($probe->currentToken())
        ->and($claimed->attempts)->toBe(1);

    // The same job can't be claimed twice: the guarded UPDATE's WHERE
    // state='pending' no longer matches.
    expect($jobs->claimNextPending())->toBeNull();
});

test('recovery leaves a stale-looking job alone while its owner process is alive', function (): void {
    $jobs = $this->container->get(JobRepository::class);
    $probe = $this->container->get(WorkerProcessProbe::class);
    $worker = $this->container->get(JobWorkerService::class);

    $job = $jobs->create(intent: JobIntent::Preflight, entityType: 'test');
    $job->state = JobState::Processing;
    $job->attempts = 1;
    $job->startedAt = DateTime::now(Timezone::UTC);
    // Well past the 120s stale threshold, under the 1800s hard-stall cap.
    $job->heartbeatAt = DateTime::now(Timezone::UTC)->minusSeconds(600);
    $job->ownerToken = $probe->currentToken();
    $job->save();

    expect($worker->recoverStaleJobs())->toBe(0);

    $job = JobRecord::findById($job->id);
    expect($job->state)->toBe(JobState::Processing);
});

test('recovery re-queues a stale job whose owner process is dead', function (): void {
    $jobs = $this->container->get(JobRepository::class);
    $probe = $this->container->get(WorkerProcessProbe::class);
    $worker = $this->container->get(JobWorkerService::class);

    $job = $jobs->create(intent: JobIntent::Preflight, entityType: 'test');
    $job->state = JobState::Processing;
    $job->attempts = 1;
    $job->startedAt = DateTime::now(Timezone::UTC);
    $job->heartbeatAt = DateTime::now(Timezone::UTC)->minusSeconds(600);
    // Our own pid with a mismatched start time reads as a dead (reused) pid;
    // on platforms without /proc the token has no start time, so fall back
    // to a pid that cannot exist.
    $token = $probe->currentToken();
    $job->ownerToken = str_contains($token, ':')
        ? ((int) explode(':', $token)[0]) . ':' . ((int) explode(':', $token)[1] + 1)
        : '99999999';
    $job->save();

    expect($worker->recoverStaleJobs())->toBe(1);

    $job = JobRecord::findById($job->id);
    expect($job->state)->toBe(JobState::Pending)
        ->and($job->ownerToken)->toBeNull()
        ->and($job->lastError)->toContain('stalled');
});

test('recovery kills an owner that has been silent past the hard-stall cap', function (): void {
    $jobs = $this->container->get(JobRepository::class);
    $probe = $this->container->get(WorkerProcessProbe::class);
    $worker = $this->container->get(JobWorkerService::class);

    $process = proc_open(['sleep', '120'], [], $pipes);
    expect($process)->not->toBeFalse();
    $pid = proc_get_status($process)['pid'];
    $token = $probe->tokenForPid($pid);
    expect($probe->isAlive($token))->toBeTrue();

    $job = $jobs->create(intent: JobIntent::Download, entityType: 'test');
    $job->state = JobState::Processing;
    $job->attempts = 1;
    $job->startedAt = DateTime::now(Timezone::UTC)->minusSeconds(3600);
    $job->heartbeatAt = DateTime::now(Timezone::UTC)->minusSeconds(3600);
    $job->ownerToken = $token;
    $job->save();

    try {
        expect($worker->recoverStaleJobs())->toBe(1);

        $job = JobRecord::findById($job->id);
        expect($job->state)->toBe(JobState::Pending)
            ->and($job->ownerToken)->toBeNull();

        // SIGKILL leaves a zombie until reaped; the probe already counts
        // zombies as dead.
        expect($probe->isAlive($token))->toBeFalse();
    } finally {
        proc_terminate($process, 9);
        proc_close($process);
    }
});
