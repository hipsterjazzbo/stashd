<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Domain\Job\JobRecord;

interface JobHandler
{
    public function handle(JobRecord $job, JobHandlerContext $context): void;

    public function intent(): \App\Domain\Job\JobIntent;
}
