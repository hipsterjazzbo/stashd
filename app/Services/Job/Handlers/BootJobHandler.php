<?php

declare(strict_types=1);

namespace App\Services\Job\Handlers;

use App\Domain\Job\JobIntent;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Services\Job\JobHandler;
use App\Services\Job\JobHandlerContext;
use App\Services\State\StateTransitionService;

final readonly class BootJobHandler implements JobHandler
{
    public function __construct(
        private StateTransitionService $transitions,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::Boot;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $context->heartbeat($job);
        $this->transitions->transitionJob($job, JobState::Ready);
    }
}
