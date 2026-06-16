<?php

declare(strict_types=1);

namespace App\Services\Command;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandType;
use App\Domain\Job\JobRecord;

interface CommandHandler
{
    public function type(): CommandType;

    /** @param array<string, mixed> $options */
    public function validate(array $options): void;

    /**
     * @param array<string, mixed> $options
     *
     * @return list<JobRecord>
     */
    public function createJobs(CommandRecord $command, array $options): array;

    /** @return array<string, mixed> */
    public function extras(CommandRecord $command, array $options): array;
}
