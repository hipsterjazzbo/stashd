<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use App\Commands\CommandDispatchService;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Commands\CommandType;
use App\Config\StashdConfig;
use App\Jobs\JobHandler;
use App\Jobs\JobHandlerContext;
use App\Jobs\JobIntent;
use App\Jobs\JobProgressUpdate;
use App\Jobs\JobRecord;
use App\Jobs\JobRepository;
use App\Jobs\JobState;
use App\Stashes\DiscoverStashInput;
use App\Stashes\PreflightExecutionResult;
use App\Stashes\StashInputId;
use App\Stashes\StashInputRecord;
use App\Stashes\StashInputRepository;
use App\Stashes\StashItemRepository;
use App\System\Activity\ActivityEventService;
use App\System\Event\EventPublisher;
use App\System\State\StateTransitionService;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final readonly class PreflightJobHandler implements JobHandler
{
    public function __construct(
        private DiscoverStashInput $executor,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
        private StashdConfig $config,
        private StashInputRepository $stashInputs,
        private StashItemRepository $stashItems,
        private CommandDispatchService $dispatch,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::Preflight;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);
        $context->progress($job, JobProgressUpdate::ofSteps(0, 1, 'Running preflight'));

        $payload = $job->payload ?? [];

        $result = $this->executor->execute($payload);
        $reviewUrl = rtrim($this->config->publicUrl, '/') . '/api/v1/stashes/preflight/' . (string) $command->id . '/review';
        $resultArray = $result->toResultArray($reviewUrl);

        $command->result = $resultArray;
        $this->commands->save($command);

        $job->progressCurrent = $result->estimatedItemCount;
        $job->progressTotal = max(1, $result->estimatedItemCount);
        $job->progressPercent = 100.0;
        $job->progressLabel = 'Preflight complete';
        $job->finishedAt = DateTime::now(Timezone::UTC);
        $this->jobs->save($job);
        $context->progress($job, JobProgressUpdate::ofSteps($job->progressCurrent, $job->progressTotal, $job->progressLabel));

        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->preflightCompleted($command, $job, $result->estimatedItemCount);
        $this->publisher->jobCompleted($job);
        $this->activity->commandCompleted($command);

        $this->ingestScheduledDiscovery($payload, $command, $result);
    }

    /**
     * A scheduled re-check only produces a preview; without this the discovered
     * items are never committed and automatic sync silently stops finding new
     * episodes. Chaining onto StashAddInput reuses the existing commit path,
     * which reuses known items and honours the stash's download policy.
     *
     * @param array<string, mixed> $payload
     */
    private function ingestScheduledDiscovery(array $payload, CommandRecord $command, PreflightExecutionResult $result): void
    {
        $input = $this->scheduledInput($payload);

        if ($input === null || ! $this->hasUningestedItems($input, $result)) {
            return;
        }

        // ponytail: commitInput re-runs discovery, so a changed input costs two
        // discovery passes in that hour. Unchanged inputs (the common case) stop
        // at the check above. Upgrade path if provider quota gets tight: have
        // commitInput trust the preflight result already stored on the command.
        $this->dispatch->dispatch(CommandType::StashAddInput, [
            'stash_id' => $input->stashId->toString(),
            'preflight_command_id' => (string) $command->id,
            // Provider option keys are opaque identifiers; round-trip the
            // input's stored options so filters keep applying to new items.
            'options' => $input->options?->toArray() ?? [],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function scheduledInput(array $payload): ?StashInputRecord
    {
        $inputId = $payload['stash_input_id'] ?? null;

        if (! is_string($inputId) || $inputId === '' || ! StashInputId::isValid($inputId)) {
            return null;
        }

        return $this->stashInputs->find(StashInputId::parse($inputId));
    }

    /**
     * Mirrors what the commit path treats as new: a stash item existing for the
     * media item anywhere in the stash, not just under this input.
     */
    private function hasUningestedItems(StashInputRecord $input, PreflightExecutionResult $result): bool
    {
        $known = [];

        foreach ($this->stashItems->listForStash($input->stashId) as $stashItem) {
            $known[$stashItem->mediaItem->providerKey . "\0" . $stashItem->mediaItem->providerItemId] = true;
        }

        foreach ($result->discoveredItems as $item) {
            $providerItemId = $item['provider_item_id'] ?? null;

            if (! is_string($providerItemId) || $providerItemId === '') {
                continue;
            }

            if (! isset($known[$input->providerKey . "\0" . $providerItemId])) {
                return true;
            }
        }

        return false;
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Preflight job is missing commandId.');
        }

        return $this->commands->find($job->commandId)
            ?? throw new \RuntimeException('Preflight command not found.');
    }
}
