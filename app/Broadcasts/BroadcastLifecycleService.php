<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Broadcasts\Podcasts\PodcastTokenRotationResult;
use App\Broadcasts\Podcasts\PodcastTokenService;
use App\System\State\StateTransitionService;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

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
        private BroadcastPluginRegistry $plugins,
        private BroadcastTriggerService $triggers,
        private PodcastTokenService $podcastTokens,
        private StateTransitionService $transitions,
    ) {
    }

    public function plan(BroadcastId $broadcastId): BroadcastPlan
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        $plan = $this->planOnly($broadcast);
        $broadcast->lastPlannedAt = DateTime::now(Timezone::UTC);
        $this->broadcasts->save($broadcast);

        return $plan;
    }

    public function rebuild(BroadcastId $broadcastId): BroadcastLifecycleResult
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw \App\Broadcasts\BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        $this->transitionToProcessing($broadcast);

        $plan = $this->planOnly($broadcast);
        $broadcast->lastPlannedAt = DateTime::now(Timezone::UTC);
        $this->broadcasts->save($broadcast);

        $publish = $this->publishOnly($broadcast, $plan);
        $broadcast->lastBuiltAt = DateTime::now(Timezone::UTC);
        $broadcast->lastError = null;
        $this->broadcasts->save($broadcast);

        $verify = $this->verifyOnly($broadcast);
        $broadcast->lastVerifiedAt = DateTime::now(Timezone::UTC);
        $this->applyVerifyState($broadcast, $verify);
        $this->broadcasts->save($broadcast);

        $trigger = null;

        if ($verify->ok && $this->shouldAutoTrigger($broadcast)) {
            $trigger = $this->triggers->execute($broadcast, 'post_rebuild')->toArray();
        }

        return new BroadcastLifecycleResult(
            plan: $plan->toArray(),
            publish: $publish->toArray(),
            verify: $verify->toArray(),
            trigger: $trigger,
        );
    }

    public function verify(BroadcastId $broadcastId): BroadcastVerifyResult
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        $verify = $this->verifyOnly($broadcast);
        $broadcast->lastVerifiedAt = DateTime::now(Timezone::UTC);
        $this->applyVerifyState($broadcast, $verify);
        $this->broadcasts->save($broadcast);

        return $verify;
    }

    public function prune(BroadcastId $broadcastId): BroadcastPruneResult
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        $context = $this->contextFactory->build($broadcast);
        $plugin = $this->resolvePlugin($context->broadcast->type);

        return $plugin->plugin->prune($context);
    }

    public function trigger(BroadcastId $broadcastId): BroadcastTriggerResult
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        return $this->triggers->execute($broadcast, 'manual');
    }

    public function rotateToken(BroadcastId $broadcastId): PodcastTokenRotationResult
    {
        $broadcast = $this->broadcasts->find($broadcastId)
            ?? throw BroadcastException::withCode('broadcast_not_found', 'Broadcast not found.');

        if ($broadcast->type !== 'podcast') {
            throw BroadcastException::withCode(
                'broadcast_token_rotation_unsupported',
                'Token rotation is only supported for podcast broadcasts.',
            );
        }

        return $this->podcastTokens->rotateBroadcastToken($broadcast);
    }

    private function planOnly(BroadcastRecord $broadcast): BroadcastPlan
    {
        $context = $this->contextFactory->build($broadcast);
        $plugin = $this->resolvePlugin($context->broadcast->type);

        return $plugin->plugin->plan($context);
    }

    private function publishOnly(BroadcastRecord $broadcast, ?BroadcastPlan $plan = null): \App\Broadcasts\BroadcastPublishResult
    {
        $context = $this->contextFactory->build($broadcast);
        $plugin = $this->resolvePlugin($context->broadcast->type);
        $plan ??= $plugin->plugin->plan($context);

        return $plugin->plugin->publish($context, $plan);
    }

    private function verifyOnly(BroadcastRecord $broadcast): BroadcastVerifyResult
    {
        $context = $this->contextFactory->build($broadcast);
        $plugin = $this->resolvePlugin($context->broadcast->type);

        return $plugin->plugin->verify($context);
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

    private function resolvePlugin(string $type): DiscoveredPlugin
    {
        $plugin = $this->plugins->findByKey($type);

        if ($plugin === null) {
            throw new \InvalidArgumentException("Unknown broadcast type: {$type}");
        }

        return $plugin;
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
