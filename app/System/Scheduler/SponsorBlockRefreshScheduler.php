<?php

declare(strict_types=1);

namespace App\System\Scheduler;

use App\Broadcasts\SponsorBlockRefreshRepository;
use App\Commands\CommandDispatchService;
use App\Commands\CommandType;
use App\Jobs\JobIntent;
use App\Jobs\JobRepository;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final readonly class SponsorBlockRefreshScheduler
{
    public function __construct(
        private SponsorBlockRefreshRepository $refreshes,
        private JobRepository $jobs,
        private CommandDispatchService $dispatch,
    ) {
    }

    public function scheduleDueRefresh(): bool
    {
        if ($this->refreshes->listDue(DateTime::now(Timezone::UTC)) === [] || $this->jobs->hasPendingOrProcessingIntent(JobIntent::SponsorBlockRefresh)) {
            return false;
        }

        $this->dispatch->dispatch(CommandType::SystemSponsorBlockRefresh, []);

        return true;
    }
}
