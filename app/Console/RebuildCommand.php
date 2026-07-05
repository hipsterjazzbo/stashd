<?php

declare(strict_types=1);

namespace App\Console;

use Symfony\Component\Process\Process;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\Middleware\CautionMiddleware;
use Tempest\Console\Middleware\ForceMiddleware;
use Tempest\Framework\Commands\MigrateFreshCommand;

final readonly class RebuildCommand
{
    use HasConsole;

    private const array SUPERVISED_ROLES = ['worker', 'scheduler', 'frankenphp'];

    #[ConsoleCommand(
        name: 'stashd:rebuild',
        description: 'Dev convenience: wipe the database, re-run stashd:boot, and restart the supervised roles for a clean, repeatable reset. Destructive.',
        middleware: [ForceMiddleware::class, CautionMiddleware::class],
    )]
    public function __invoke(): ExitCode
    {
        // Stop the roles first so nothing holds a SQLite connection open
        // across the schema drop below. Gracefully skipped (and the restart
        // below skipped too) when supervisorctl isn't reachable -- plain
        // `php tempest serve` dev setups without supervisord are unaffected.
        $supervised = $this->console->task('Stop worker/scheduler/frankenphp', $this->supervisorctl('stop'));

        $this->console->call(MigrateFreshCommand::class);
        $this->console->call(BootCommand::class);

        if ($supervised) {
            $this->console->task('Restart worker/scheduler/frankenphp', $this->supervisorctl('start'));
        } else {
            $this->console->warning('supervisorctl unreachable -- worker/scheduler/frankenphp were not restarted.');
        }

        return ExitCode::SUCCESS;
    }

    private function supervisorctl(string $action): Process
    {
        return new Process(['supervisorctl', $action, ...self::SUPERVISED_ROLES]);
    }
}
