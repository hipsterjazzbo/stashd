<?php

declare(strict_types=1);

namespace App\System\RoadRunner;

use Tempest\Console\ExitCode;
use Tempest\Process\ProcessExecutor;

final readonly class RoadRunnerProcessLauncher
{
    public function __construct(
        private ProcessExecutor $processes,
    ) {
    }

    public function serve(): ExitCode
    {
        $root = dirname(__DIR__, 3);
        $config = $root . '/.rr.yaml';
        $binary = $root . '/rr';

        if (! is_executable($binary)) {
            throw new \RuntimeException(
                'RoadRunner binary not found. Run `vendor/bin/rr get` in the project root.',
            );
        }

        $this->processes->run("{$binary} serve -c {$config}");

        return ExitCode::SUCCESS;
    }
}
