<?php

declare(strict_types=1);

namespace App\Console;

use App\System\Boot\BootstrapService;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Database\Config\SQLiteConfig;

final readonly class BootCommand
{
    use HasConsole;

    public function __construct(
        private BootstrapService $bootstrap,
        private SQLiteConfig $sqliteConfig,
    ) {
    }

    #[ConsoleCommand(
        name: 'stashd:boot',
        description: 'Prepare storage roots, SQLite, migrations, and storage checks',
    )]
    public function __invoke(): ExitCode
    {
        $result = $this->bootstrap->boot($this->sqliteConfig);

        $this->console->success('Stashd boot completed.');
        $this->console->keyValue('Directories created', (string) count($result['directories_created']));
        $this->console->keyValue('Boot command', $result['command_id']);
        $this->console->keyValue('Boot job', $result['job_id']);

        return ExitCode::SUCCESS;
    }
}
