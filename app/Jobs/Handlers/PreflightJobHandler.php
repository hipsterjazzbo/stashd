<?php

declare(strict_types=1);

namespace App\Jobs\Handlers;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;
use App\Commands\CommandRecord;
use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\Config\StashdConfig;
use App\Jobs\JobHandler;
use App\Jobs\JobHandlerContext;
use App\Jobs\JobIntent;
use App\Jobs\JobRecord;
use App\Jobs\JobRepository;
use App\Jobs\JobState;
use App\Stashes\DiscoverStashInput;
use App\Support\PrefixedUlid;
use App\System\Activity\ActivityEventService;
use App\System\Event\EventPublisher;
use App\System\State\StateTransitionService;

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
        $context->progress($job, 0, 1, 'Running preflight');

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        $result = $this->executor->execute($payload);
        $reviewUrl = rtrim($this->config->publicUrl, '/') . '/api/v1/stashes/preflight/' . (string) $command->id . '/review';
        $resultArray = $result->toResultArray($reviewUrl);

        $command->resultJson = json_encode($resultArray, JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        $job->progressCurrent = $result->estimatedItemCount;
        $job->progressTotal = max(1, $result->estimatedItemCount);
        $job->progressPercent = 100.0;
        $job->progressLabel = 'Preflight complete';
        $job->finishedAt = DateTime::now(Timezone::UTC);
        $this->jobs->save($job);
        $context->progress($job, $job->progressCurrent, $job->progressTotal, $job->progressLabel);

        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->preflightCompleted($command, $job, $result->estimatedItemCount);
        $this->publisher->jobCompleted($job);
        $this->activity->commandCompleted($command);
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Preflight job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new \RuntimeException('Preflight command not found.');
    }
}
