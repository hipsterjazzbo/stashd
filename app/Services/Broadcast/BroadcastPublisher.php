<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Domain\Broadcast\BroadcastPlan;
use App\Domain\Broadcast\BroadcastPublishResult;
use App\Domain\Support\PrefixedUlid;

final readonly class BroadcastPublisher
{
    public function __construct(
        private BroadcastContextFactory $contextFactory,
        private BroadcastTypeRegistry $types,
    ) {
    }

    public function publish(PrefixedUlid $broadcastId, ?BroadcastPlan $plan = null): BroadcastPublishResult
    {
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);
        $plan ??= $handler->plan($context);

        return $handler->publish($context, $plan);
    }
}
