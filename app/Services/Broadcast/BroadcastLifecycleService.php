<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Domain\Broadcast\BroadcastPlan;
use App\Domain\Broadcast\BroadcastPruneResult;
use App\Domain\Broadcast\BroadcastState;
use App\Domain\Broadcast\BroadcastVerifyResult;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\BroadcastRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Services\State\StateTransitionService;

final readonly class BroadcastLifecycleResult
{
    /** @param array<string, mixed> $plan */
    public function __construct(
        public ?array $plan = null,
        public ?array $publish = null,
        public ?array $verify = null,
        public ?array $prune = null,
        public ?array $trigger = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'plan' => $this->plan,
            'publish' => $this->publish,
            'verify' => $this->verify,
            'prune' => $this->prune,
            'trigger' => $this->trigger,
        ], static fn ($value): bool => $value !== null);
    }
}

final readonly class BroadcastLifecycleService
{
    public function __construct(
        private BroadcastRepository $broadcasts,
        private BroadcastPlanner $planner,
        private BroadcastPublisher $publisher,
        private BroadcastVerifier $verifier,
        private BroadcastPruner $pruner,
        private BroadcastTriggerService $triggers,
        private StateTransitionService $transitions,
    ) {
    }

    public function plan(PrefixedUlid $broadcastId): BroadcastPlan
    {
        $plan = $this->planner->plan($broadcastId);
        $broadcast = $this->broadcasts->find($broadcastId);

        if ($broadcast !== null) {
            $broadcast->lastPlannedAt = RecordTimestamps::now();
            $this->broadcasts->save($broadcast);
        }

        return $plan;
    }

    public function rebuild(PrefixedUlid $broadcastId): BroadcastLifecycleResult
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw \App\Domain\Broadcast\BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        $this->transitionToProcessing($broadcast);

        $plan = $this->planner->plan($broadcastId);
        $broadcast->lastPlannedAt = RecordTimestamps::now();
        $this->broadcasts->save($broadcast);

        $publish = $this->publisher->publish($broadcastId, $plan);
        $broadcast->lastBuiltAt = RecordTimestamps::now();
        $broadcast->lastError = null;
        $this->broadcasts->save($broadcast);

        $verify = $this->verifier->verify($broadcastId);
        $broadcast->lastVerifiedAt = RecordTimestamps::now();
        $this->applyVerifyState($broadcast, $verify);
        $this->broadcasts->save($broadcast);

        $trigger = null;

        if ($verify->ok && $this->shouldAutoTrigger($broadcast)) {
            $trigger = $this->triggers->execute($broadcastId, 'post_rebuild')->toArray();
        }

        return new BroadcastLifecycleResult(
            plan: $plan->toArray(),
            publish: $publish->toArray(),
            verify: $verify->toArray(),
            trigger: $trigger,
        );
    }

    public function verify(PrefixedUlid $broadcastId): BroadcastVerifyResult
    {
        $verify = $this->verifier->verify($broadcastId);
        $broadcast = $this->broadcasts->find($broadcastId);

        if ($broadcast !== null) {
            $broadcast->lastVerifiedAt = RecordTimestamps::now();
            $this->applyVerifyState($broadcast, $verify);
            $this->broadcasts->save($broadcast);
        }

        return $verify;
    }

    public function prune(PrefixedUlid $broadcastId): BroadcastPruneResult
    {
        return $this->pruner->prune($broadcastId);
    }

    public function trigger(PrefixedUlid $broadcastId): BroadcastTriggerResult
    {
        return $this->triggers->execute($broadcastId, 'manual');
    }

    private function shouldAutoTrigger(\App\Domain\Broadcast\BroadcastRecord $broadcast): bool
    {
        if ($broadcast->settingsJson === null) {
            return false;
        }

        $settings = json_decode($broadcast->settingsJson, true);

        if (! is_array($settings)) {
            return false;
        }

        return (bool) ($settings['auto_trigger_scan'] ?? false);
    }

    private function transitionToProcessing(\App\Domain\Broadcast\BroadcastRecord $broadcast): void
    {
        if ($broadcast->state === BroadcastState::Processing) {
            return;
        }

        $this->transitions->transitionBroadcast($broadcast, BroadcastState::Processing);
    }

    private function applyVerifyState(
        \App\Domain\Broadcast\BroadcastRecord $broadcast,
        BroadcastVerifyResult $verify,
    ): void {
        if ($verify->ok) {
            if ($broadcast->state !== BroadcastState::Ready) {
                $this->transitions->transitionBroadcast($broadcast, BroadcastState::Ready);
            }

            return;
        }

        $broadcast->lastError = 'broadcast_verification_failed';

        if ($broadcast->state !== BroadcastState::Stale) {
            $this->transitions->transitionBroadcast($broadcast, BroadcastState::Stale);
        }
    }
}
