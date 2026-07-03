<?php

declare(strict_types=1);

namespace App\Console;

use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\Middleware\CautionMiddleware;
use Tempest\Console\Middleware\ForceMiddleware;
use Tempest\Framework\Commands\MigrateFreshCommand;

final readonly class RebuildCommand
{
    use HasConsole;

    #[ConsoleCommand(
        name: 'stashd:rebuild',
        description: 'Dev convenience: wipe the database and re-run stashd:boot for a clean, repeatable reset. Destructive.',
        middleware: [ForceMiddleware::class, CautionMiddleware::class],
    )]
    public function __invoke(): ExitCode
    {
        $this->console->call(MigrateFreshCommand::class);
        $this->console->call(BootCommand::class);

        return ExitCode::SUCCESS;
    }
}
