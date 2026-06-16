<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Broadcasts\Podcasts\PodcastTokenRotationResult;
use App\Broadcasts\Podcasts\PodcastTokenService;
use App\Support\PrefixedUlid;
use App\Support\RecordTimestamps;
use App\System\State\StateTransitionService;

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
        private BroadcastContextFactory $contextFactory,
        private BroadcastTypeRegistry $types,
        private BroadcastTriggerService $triggers,
        private PodcastTokenService $podcastTokens,
        private StateTransitionService $transitions,
    ) {
    }

    public function plan(PrefixedUlid $broadcastId): BroadcastPlan
    {
        $plan = $this->planOnly($broadcastId);
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
            ?? throw \App\Broadcasts\BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        $this->transitionToProcessing($broadcast);

        $plan = $this->planOnly($broadcastId);
        $broadcast->lastPlannedAt = RecordTimestamps::now();
        $this->broadcasts->save($broadcast);

        $publish = $this->publishOnly($broadcastId, $plan);
        $broadcast->lastBuiltAt = RecordTimestamps::now();
        $broadcast->lastError = null;
        $this->broadcasts->save($broadcast);

        $verify = $this->verifyOnly($broadcastId);
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
        $verify = $this->verifyOnly($broadcastId);
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
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);

        return $handler->prune($context);
    }

    public function trigger(PrefixedUlid $broadcastId): BroadcastTriggerResult
    {
        return $this->triggers->execute($broadcastId, 'manual');
    }

    public function rotateToken(PrefixedUlid $broadcastId): PodcastTokenRotationResult
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        if (! $this->podcastTokens->supports($broadcast)) {
            throw BroadcastException::withCode(
                'broadcast_token_rotation_unsupported',
                'Token rotation is only supported for podcast broadcasts.',
            );
        }

        return $this->podcastTokens->rotateBroadcastToken($broadcast);
    }

    private function planOnly(PrefixedUlid $broadcastId): BroadcastPlan
    {
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);

        return $handler->plan($context);
    }

    private function publishOnly(PrefixedUlid $broadcastId, ?BroadcastPlan $plan = null): \App\Broadcasts\BroadcastPublishResult
    {
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);
        $plan ??= $handler->plan($context);

        return $handler->publish($context, $plan);
    }

    private function verifyOnly(PrefixedUlid $broadcastId): BroadcastVerifyResult
    {
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);

        return $handler->verify($context);
    }

    private function shouldAutoTrigger(\App\Broadcasts\BroadcastRecord $broadcast): bool
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

    private function transitionToProcessing(\App\Broadcasts\BroadcastRecord $broadcast): void
    {
        if ($broadcast->state === BroadcastState::Processing) {
            return;
        }

        $this->transitions->transitionBroadcast($broadcast, BroadcastState::Processing);
    }

    private function applyVerifyState(
        \App\Broadcasts\BroadcastRecord $broadcast,
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
