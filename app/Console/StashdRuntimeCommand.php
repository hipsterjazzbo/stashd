<?php

declare(strict_types=1);

namespace App\Console;

use App\Infrastructure\RoadRunner\RoadRunnerProcessLauncher;
use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Process\ProcessExecutor;

final readonly class StashdRuntimeCommand
{
    use HasConsole;

    public function __construct(
        private ProcessExecutor $processes,
        private RoadRunnerProcessLauncher $roadRunner,
    ) {
    }

    #[ConsoleCommand(
        name: 'stashd',
        description: 'Stashd runtime roles: all, serve, worker, scheduler',
    )]
    public function __invoke(
        #[ConsoleArgument(description: 'Role to run', aliases: ['role'])]
        string $role = 'all',
    ): ExitCode {
        return match ($role) {
            'all' => $this->runAll(),
            'serve' => $this->roadRunner->serve(),
            'worker' => $this->runWorker(),
            'scheduler' => $this->runScheduler(),
            default => $this->unknownRole($role),
        };
    }

    private function runAll(): ExitCode
    {
        $this->console->info('Starting Stashd all-in-one runtime (supervisord expected in Docker).');
        $this->console->warn('Local dev: run `stashd serve`, `stashd worker`, and `stashd scheduler` in separate terminals.');

        return $this->roadRunner->serve();
    }

    private function runWorker(): ExitCode
    {
        $this->console->info('Job worker started. Polling for pending jobs…');

        while (true) {
            $this->processes->run('php tempest stashd:worker-tick');
            sleep(2);
        }
    }

    private function runScheduler(): ExitCode
    {
        $this->console->info('Scheduler started.');

        while (true) {
            $this->processes->run('php tempest schedule:run');
            sleep(60);
        }
    }

    private function unknownRole(string $role): ExitCode
    {
        $this->console->error("Unknown stashd role: {$role}");
        $this->console->info('Valid roles: all, serve, worker, scheduler');

        return ExitCode::ERROR;
    }
}
