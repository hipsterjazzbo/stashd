<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Commands\CommandRepository;
use App\Commands\CommandState;
use App\System\Activity\ActivityEventService;
use App\System\Event\EventPublisher;
use App\System\State\StateTransitionService;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final readonly class JobWorkerService implements JobWorkerCallbacks
{
    private const int STALE_SECONDS = 120;

    /** Backoff before retrying a rate-limited/bot-checked download, indexed by attempt number (capped at the last value). */
    private const array RETRY_BACKOFF_SECONDS = [30, 120, 480];

    public function __construct(
        private JobRepository $jobs,
        private CommandRepository $commands,
        private StateTransitionService $transitions,
        private JobHandlerRegistry $handlers,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function recoverStaleJobs(): int
    {
        $staleBefore = DateTime::now(Timezone::UTC)->minusSeconds(self::STALE_SECONDS);
        $recovered = 0;

        foreach ($this->jobs->listProcessingStale($staleBefore) as $job) {
            $message = 'Job stalled without heartbeat and was recovered.';
            $job->lastError = $message;

            if ($job->attempts >= $job->maxAttempts) {
                $this->transitions->transitionJob($job, JobState::Failed);
                $job->finishedAt = DateTime::now(Timezone::UTC);
                $this->jobs->save($job);
                $this->failCommandIfNeeded($job, $message);
                $this->activity->jobFailed($job, $message);
                $this->publisher->jobFailed($job);
            } else {
                $this->transitions->transitionJob($job, JobState::Pending);
                $job->startedAt = null;
                $job->heartbeatAt = null;
                $this->jobs->save($job);
            }

            $recovered++;
        }

        return $recovered;
    }

    public function processNextJob(): bool
    {
        $this->recoverStaleJobs();

        $job = $this->jobs->claimNextPending($this->transitions);

        if ($job === null) {
            return false;
        }

        $this->activity->jobStarted($job);
        $this->publisher->jobProgress($job);

        $handler = $this->handlers->handlerFor($job->intent);

        if ($handler === null) {
            $this->failJob($job, 'No handler registered for intent: ' . $job->intent->value);

            return true;
        }

        try {
            $handler->handle($job, new JobHandlerContext($this));
        } catch (\App\Downloads\DownloadException $exception) {
            $error = $exception->errorCode . ': ' . $exception->getMessage();

            if ($exception->retryable && $job->attempts < $job->maxAttempts) {
                $this->retryJob($job, $error);
            } else {
                $this->failJob($job, $error);
                $this->activity->downloadFailed($job, $exception->errorCode, $exception->getMessage());
            }
        } catch (\App\Transcoding\TranscodeException $exception) {
            $this->failJob($job, $exception->errorCode . ': ' . $exception->getMessage());
            $this->activity->podcastAudioTranscodeFailed($job, $exception->errorCode, $exception->getMessage());
        } catch (\App\Broadcasts\BroadcastException $exception) {
            $this->failJob($job, $exception->errorCode . ': ' . $exception->getMessage());
        } catch (\App\MediaServers\MediaServerException $exception) {
            $this->failJob($job, $exception->errorCode . ': ' . $exception->getMessage());
        } catch (\App\Providers\ProviderException $exception) {
            $this->failJob($job, $exception->errorCode . ': ' . $exception->getMessage());
        } catch (\Throwable $throwable) {
            $this->failJob($job, $throwable->getMessage());
        }

        return true;
    }

    public function heartbeat(JobRecord $job): void
    {
        $job->heartbeatAt = DateTime::now(Timezone::UTC);
        $this->jobs->save($job);
    }

    public function progress(JobRecord $job, JobProgressUpdate $update): void
    {
        $job->progressCurrent = $update->current;
        $job->progressTotal = $update->total;
        $job->progressPercent = $update->percent;
        $job->progressLabel = $update->label;
        $job->progressEtaSeconds = $update->etaSeconds;
        $job->progressRate = $update->rate;
        $job->heartbeatAt = DateTime::now(Timezone::UTC);
        $this->jobs->save($job);
        $this->publisher->jobProgress($job);
    }

    private function retryJob(JobRecord $job, string $error): void
    {
        if ($job->state !== JobState::Processing) {
            return;
        }

        $index = min(max($job->attempts - 1, 0), count(self::RETRY_BACKOFF_SECONDS) - 1);

        $job->lastError = $error;
        $job->startedAt = null;
        $job->heartbeatAt = null;
        $job->scheduledAt = DateTime::now(Timezone::UTC)->plusSeconds(self::RETRY_BACKOFF_SECONDS[$index]);
        $this->jobs->save($job);
        $this->transitions->transitionJob($job, JobState::Pending);
    }

    private function failJob(JobRecord $job, string $error): void
    {
        if ($job->state === JobState::Failed) {
            return;
        }

        if ($job->state !== JobState::Processing) {
            return;
        }

        $job->lastError = $error;
        $job->finishedAt = DateTime::now(Timezone::UTC);
        $this->jobs->save($job);
        $this->transitions->transitionJob($job, JobState::Failed);
        $this->failCommandIfNeeded($job, $error);
        $this->activity->jobFailed($job, $error);
        $this->publisher->jobFailed($job);
    }

    private function failCommandIfNeeded(JobRecord $job, string $error): void
    {
        if ($job->commandId === null) {
            return;
        }

        $command = $this->commands->find($job->commandId);

        if ($command === null || $command->state === CommandState::Failed || $command->state === CommandState::Completed) {
            return;
        }

        $this->transitions->transitionCommand($command, CommandState::Failed);
        $this->activity->commandFailed($command, $error);
    }
}
