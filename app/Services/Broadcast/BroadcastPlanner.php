<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Domain\Broadcast\BroadcastPlan;
use App\Domain\Support\PrefixedUlid;

final readonly class BroadcastPlanner
{
    public function __construct(
        private BroadcastContextFactory $contextFactory,
        private BroadcastTypeRegistry $types,
    ) {
    }

    public function plan(PrefixedUlid $broadcastId): BroadcastPlan
    {
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);

        return $handler->plan($context);
    }
}
