<?php

declare(strict_types=1);

namespace App\Jobs;

interface JobWorkerCallbacks
{
    public function heartbeat(JobRecord $job): void;

    public function progress(JobRecord $job, JobProgressUpdate $update): void;
}
