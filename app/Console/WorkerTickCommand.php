<?php

declare(strict_types=1);

namespace App\Console;

use App\Services\Job\JobWorkerService;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class WorkerTickCommand
{
    use HasConsole;

    public function __construct(
        private JobWorkerService $worker,
    ) {
    }

    #[ConsoleCommand(
        name: 'stashd:worker-tick',
        description: 'Process one pending job if available',
    )]
    public function __invoke(): ExitCode
    {
        $processed = $this->worker->processNextJob();

        return $processed ? ExitCode::SUCCESS : ExitCode::SUCCESS;
    }
}
