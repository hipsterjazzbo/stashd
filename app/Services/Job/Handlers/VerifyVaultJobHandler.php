<?php

declare(strict_types=1);

namespace App\Services\Job\Handlers;

use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandState;
use App\Domain\Job\JobIntent;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Services\Activity\ActivityEventService;
use App\Services\Event\EventPublisher;
use App\Services\Job\JobHandler;
use App\Services\Job\JobHandlerContext;
use App\Services\State\StateTransitionService;
use App\Services\Vault\VaultVerifyService;

final readonly class VerifyVaultJobHandler implements JobHandler
{
    public function __construct(
        private VaultVerifyService $verify,
        private CommandRepository $commands,
        private JobRepository $jobs,
        private StateTransitionService $transitions,
        private ActivityEventService $activity,
        private EventPublisher $publisher,
    ) {
    }

    public function intent(): JobIntent
    {
        return JobIntent::VerifyVault;
    }

    public function handle(JobRecord $job, JobHandlerContext $context): void
    {
        $command = $this->requireCommand($job);
        $this->transitions->transitionCommand($command, CommandState::Running);
        $context->heartbeat($job);

        $payload = $job->payloadJson === null
            ? []
            : json_decode($job->payloadJson, true, flags: JSON_THROW_ON_ERROR);

        if (isset($payload['asset_id']) && is_string($payload['asset_id']) && $payload['asset_id'] !== '') {
            $outcome = $this->verify->verifyAsset(PrefixedUlid::parse($payload['asset_id']));
            $result = [
                'scope' => 'asset',
                'asset_id' => $payload['asset_id'],
                'outcome' => $outcome->value,
            ];
        } else {
            $verifyResult = $this->verify->verifyAll();
            $result = ['scope' => 'vault', ...$verifyResult->toArray()];
        }

        $command->resultJson = json_encode($result, JSON_THROW_ON_ERROR);
        $this->commands->save($command);

        $job->progressCurrent = 1;
        $job->progressTotal = 1;
        $job->progressPercent = 100.0;
        $job->progressLabel = 'Vault verification complete';
        $job->finishedAt = RecordTimestamps::now();
        $this->jobs->save($job);
        $context->progress($job, 1, 1, $job->progressLabel);

        $this->transitions->transitionJob($job, JobState::Ready);
        $this->transitions->transitionCommand($command, CommandState::Completed);
        $this->activity->vaultVerifyCompleted($command, $job, $result);
        $this->publisher->jobCompleted($job);
        $this->activity->commandCompleted($command);
    }

    private function requireCommand(JobRecord $job): CommandRecord
    {
        if ($job->commandId === null) {
            throw new \RuntimeException('Verify vault job is missing commandId.');
        }

        return $this->commands->find(PrefixedUlid::parse($job->commandId))
            ?? throw new \RuntimeException('Verify vault command not found.');
    }
}
