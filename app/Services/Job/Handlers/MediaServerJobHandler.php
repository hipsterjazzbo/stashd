<?php

declare(strict_types=1);

namespace App\Services\Job\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandState;
use App\Domain\Job\JobIntent;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Domain\MediaServer\MediaServerException;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Services\Activity\ActivityEventService;
use App\Services\Event\EventPublisher;
use App\Services\Job\JobHandler;
use App\Services\Job\JobHandlerContext;
use App\Services\MediaServer\MediaServerConnectionService;
use App\Services\State\StateTransitionService;

final readonly class MediaServerJobHandler implements JobHandler
{
    public function __construct(
        private MediaServerConnectionService $connections,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::MediaServer;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        $connectionId = PrefixedUlid::parse((string) ($payload['media_server_connection_id'] ?? ''));
        $action = (string) ($payload['action'] ?? 'test_connection');

        $job->progressTotal = 2;
        $this->jobs->save($job);

        $result = match ($action) {
            'test_connection' => $this->handleTestConnection($command, $job, $context, $connectionId),
            'list_libraries' => $this->handleListLibraries($command, $job, $context, $connectionId),
            default => throw MediaServerException::withCode('media_server_action_unsupported', 'Unsupported media server action.'),
        };

        $command->resultJson = json_encode($result, JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        $job->progressCurrent = 2;
        $job->progressPercent = 100.0;
        $job->progressLabel = 'Media server ' . $action . ' complete';
        $job->finishedAt = RecordTimestamps::now();
        $this->jobs->save($job);
        $context->progress($job, 2, 2, $job->progressLabel);

        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->commandCompleted($command);
        $this->publisher->jobCompleted($job);
    }

    /** @return array<string, mixed> */
    private function handleTestConnection(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $connectionId,
    ): array {
        $context->progress($job, 1, 2, 'Testing media server connection');
        $status = $this->connections->testConnection($connectionId);
        $this->activity->mediaServerTestCompleted($command, $job, $connectionId, $status->toArray());

        return ['status' => $status->toArray()];
    }

    /** @return array<string, mixed> */
    private function handleListLibraries(
        CommandRecord $command,
        JobRecord $job,
        JobHandlerContext $context,
        PrefixedUlid $connectionId,
    ): array {
        $context->progress($job, 1, 2, 'Listing media server libraries');
        $libraries = $this->connections->listLibraries($connectionId);

        return [
            'libraries' => array_map(static fn ($library): array => $library->toArray(), $libraries),
        ];
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Media server job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new \RuntimeException('Media server command not found.');
    }
}
