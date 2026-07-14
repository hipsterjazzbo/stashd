<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Broadcasts\BroadcastItemRepository;
use App\Broadcasts\BroadcastRepository;
use App\Broadcasts\SponsorBlockClient;
use App\Broadcasts\SponsorBlockRefreshRepository;
use App\Broadcasts\SponsorBlockSettings;
use App\Commands\CommandDispatchService;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Commands\CommandType;
use App\Jobs\JobHandler;
use App\Jobs\JobHandlerContext;
use App\Jobs\JobIntent;
use App\Jobs\JobProgressUpdate;
use App\Jobs\JobRecord;
use App\Jobs\JobRepository;
use App\Jobs\JobState;
use App\Providers\SponsorBlockProviderEligibility;
use App\System\Activity\ActivityEventService;
use App\System\Event\EventPublisher;
use App\System\State\StateTransitionService;
use App\Timeline\SponsorBlockTimelineSynchronizer;
use App\Vault\MediaItemRepository;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final readonly class SponsorBlockRefreshJobHandler implements JobHandler
{
    public function __construct(
        private SponsorBlockRefreshRepository $refreshes,
        private BroadcastItemRepository $items,
        private BroadcastRepository $broadcasts,
        private MediaItemRepository $mediaItems,
        private SponsorBlockProviderEligibility $providers,
        private SponsorBlockClient $client,
        private SponsorBlockTimelineSynchronizer $timeline,
        private CommandDispatchService $dispatch,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::SponsorBlockRefresh;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $due = $this->refreshes->listDue(DateTime::now(Timezone::UTC));
        $changed = 0;
        $failed = 0;

        foreach ($due as $refresh) {
            $item = $this->items->find($refresh->broadcastItemId);
            $broadcast = $item === null ? null : $this->broadcasts->find($item->broadcastId);
            $mediaItem = $item === null ? null : $this->mediaItems->find($item->mediaItemId);

            if ($item === null || $broadcast === null || $mediaItem === null) {
                $this->refreshes->complete($refresh);

                continue;
            }

            $settings = SponsorBlockSettings::fromBroadcastSettings($broadcast->settings ?? []);

            if (! $settings->enabled || ! $this->providers->supports($mediaItem)) {
                $this->refreshes->complete($refresh);

                continue;
            }

            try {
                if ($this->timeline->sync($item->mediaItemId, $this->client->fetch($mediaItem->providerItemId, $settings->categories))) {
                    $this->dispatch->dispatch(CommandType::BroadcastRebuildItem, ['broadcast_item_id' => (string) $item->id]);
                    $changed++;
                }

                $this->refreshes->reschedule($refresh, DateTime::now(Timezone::UTC));
            } catch (\Throwable) {
                $this->refreshes->reschedule($refresh, DateTime::now(Timezone::UTC), 'sponsorblock_fetch_failed');
                $failed++;
            }
        }

        $command->result = ['checked_count' => count($due), 'changed_count' => $changed, 'failed_count' => $failed];
        $this->commands->save($command);
        $job->progressCurrent = 1;
        $job->progressTotal = 1;
        $job->progressPercent = 100.0;
        $job->progressLabel = 'SponsorBlock refresh complete';
        $job->finishedAt = DateTime::now(Timezone::UTC);
        $this->jobs->save($job);
        $context->progress($job, JobProgressUpdate::ofSteps(1, 1, $job->progressLabel));
        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->commandCompleted($command);
        $this->publisher->jobCompleted($job);
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('SponsorBlock refresh job is missing commandId.');
        }

        return $this->commands->find($job->commandId)
            ?? throw new \RuntimeException('SponsorBlock refresh command not found.');
    }
}
