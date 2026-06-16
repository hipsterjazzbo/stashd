<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Domain\Job\JobIntent;

final readonly class JobHandlerRegistry
{
    /** @param list<JobHandler> $handlers */
    public function __construct(
        private array $handlers,
    ) {
    }

    public function handlerFor(JobIntent $intent): ?JobHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->intent() === $intent) {
                return $handler;
            }
        }

        return null;
    }
}
