<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Domain\Job\JobRecord;

interface JobWorkerCallbacks
{
    public function heartbeat(JobRecord $job): void;

    public function progress(JobRecord $job, int $current, int $total, string $label): void;
}
