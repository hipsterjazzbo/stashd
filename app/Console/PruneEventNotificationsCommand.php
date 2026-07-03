<?php

declare(strict_types=1);

namespace App\Console;

use App\System\Boot\SqliteConfigurator;
use App\System\Event\EventNotificationRepository;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\Schedule;
use Tempest\Console\Scheduler\Every;
use Tempest\Database\Config\SQLiteConfig;

final readonly class PruneEventNotificationsCommand
{
    use HasConsole;

    // event_notifications is a short-lived SSE relay (App\System\Event\
    // EventsController), not durable history -- a connection only ever
    // needs the last few seconds of backlog to resume after a reconnect.
    // An hour is a wide safety margin against that, not a tight budget,
    // while still bounding table growth over a long uptime.
    private const int RETENTION_HOURS = 1;

    public function __construct(
        private EventNotificationRepository $notifications,
        private SqliteConfigurator $sqlite,
        private SQLiteConfig $sqliteConfig,
    ) {
    }

    #[ConsoleCommand(
        name: 'stashd:prune-events',
        description: 'Deletes event_notifications rows past the SSE retention window',
    )]
    #[Schedule(Every::HOUR)]
    public function __invoke(): ExitCode
    {
        // Fresh CLI process every tick (schedule:run, invoked every 60s by
        // App\Console\StashdRuntimeCommand::runScheduler) -- same missing
        // busy_timeout pragma as SchedulerTickCommand.
        $this->sqlite->configure($this->sqliteConfig);

        $pruned = $this->notifications->pruneOlderThan(self::RETENTION_HOURS);

        if ($pruned > 0) {
            $this->console->info("Pruned {$pruned} old event notification(s).");
        }

        return ExitCode::SUCCESS;
    }
}
