<?php

declare(strict_types=1);

namespace App\System\Scheduler;

use App\Commands\CommandDispatchService;
use App\Commands\CommandType;
use App\Stashes\StashInputRepository;
use App\Stashes\SyncMode;
use App\Support\RecordTimestamps;

final readonly class RoutineDiscoveryScheduler
{
    private const int CHECK_INTERVAL_SECONDS = 3600;

    public function __construct(
        private StashInputRepository $inputs,
        private CommandDispatchService $dispatch,
    ) {
    }

    public function runDueChecks(): int
    {
        $now = RecordTimestamps::now();
        $scheduled = 0;

        foreach ($this->inputs->listDueForAutomaticSync($now) as $input) {
            $this->dispatch->dispatch(
                CommandType::StashPreflight,
                [
                    'source_uri' => $input->sourceUri,
                    'source_title' => $input->title,
                    'origin' => 'scheduler',
                    'stash_input_id' => (string) $input->id,
                ],
            );

            $input->lastCheckedAt = $now;
            $input->nextCheckAt = gmdate('Y-m-d H:i:s', time() + self::CHECK_INTERVAL_SECONDS);
            $input->syncMode = $input->syncMode ?? SyncMode::Automatic;
            $this->inputs->save($input);
            $scheduled++;
        }

        return $scheduled;
    }
}
