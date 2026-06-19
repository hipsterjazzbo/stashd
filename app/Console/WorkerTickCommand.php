<?php

declare(strict_types=1);

namespace App\Console;

use App\Jobs\JobWorkerService;
use App\System\Boot\SqliteConfigurator;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Database\Config\SQLiteConfig;

final readonly class WorkerTickCommand
{
    use HasConsole;

    public function __construct(
        private JobWorkerService $worker,
        private SqliteConfigurator $sqlite,
        private SQLiteConfig $sqliteConfig,
    ) {
    }

    #[ConsoleCommand(
        name: 'stashd:worker-tick',
        description: 'Process one pending job if available',
    )]
    public function __invoke(): ExitCode
    {
        // Fresh CLI process every tick (App\Console\StashdRuntimeCommand::runWorker
        // shells out every 2s) — its PDO connection never gets stashd:boot's
        // busy_timeout pragma, same gap as TempestPsr7Bridge::run().
        $this->sqlite->configure($this->sqliteConfig);

        $processed = $this->worker->processNextJob();

        return $processed ? ExitCode::SUCCESS : ExitCode::SUCCESS;
    }
}
