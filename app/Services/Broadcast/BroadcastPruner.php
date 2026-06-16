<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Domain\Broadcast\BroadcastPruneResult;
use App\Domain\Support\PrefixedUlid;

final readonly class BroadcastPruner
{
    public function __construct(
        private BroadcastContextFactory $contextFactory,
        private BroadcastTypeRegistry $types,
    ) {
    }

    public function prune(PrefixedUlid $broadcastId): BroadcastPruneResult
    {
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);

        return $handler->prune($context);
    }
}
