<?php

declare(strict_types=1);

namespace App\Services\Event;

use App\Domain\Activity\ActivityEventRecord;
use App\Domain\Job\JobRecord;
use App\Infrastructure\Persistence\EventNotificationRepository;
use App\Services\Secret\SecretsService;

final readonly class EventPublisher
{
    public function __construct(
        private EventNotificationRepository $notifications,
        private SecretsService $secrets,
    ) {
    }

    public function jobCreated(JobRecord $job): void
    {
        $this->notifications->publish('job.created', [
            'job_id' => (string) $job->id,
            'command_id' => $job->commandId,
            'intent' => $job->intent->value,
            'state' => $job->state->value,
        ]);
    }

    public function jobProgress(JobRecord $job): void
    {
        $this->notifications->publish('job.progress', [
            'job_id' => (string) $job->id,
            'command_id' => $job->commandId,
            'intent' => $job->intent->value,
            'progress_current' => $job->progressCurrent,
            'progress_total' => $job->progressTotal,
            'progress_percent' => $job->progressPercent,
            'progress_label' => $job->progressLabel,
        ]);
    }

    public function jobCompleted(JobRecord $job): void
    {
        $this->notifications->publish('job.completed', [
            'job_id' => (string) $job->id,
            'command_id' => $job->commandId,
            'intent' => $job->intent->value,
            'state' => $job->state->value,
        ]);
    }

    public function jobFailed(JobRecord $job): void
    {
        $this->notifications->publish('job.failed', [
            'job_id' => (string) $job->id,
            'command_id' => $job->commandId,
            'intent' => $job->intent->value,
            'state' => $job->state->value,
            'last_error' => $job->lastError === null ? null : $this->secrets->redact($job->lastError),
        ]);
    }

    public function activityCreated(ActivityEventRecord $event): void
    {
        $this->notifications->publish('activity.created', [
            'activity_id' => (string) $event->id,
            'level' => $event->level->value,
            'type' => $event->type,
            'message' => $event->message,
            'command_id' => $event->commandId,
            'job_id' => $event->jobId,
        ]);
    }
}
