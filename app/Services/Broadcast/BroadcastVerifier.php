<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Domain\Broadcast\BroadcastVerifyResult;
use App\Domain\Support\PrefixedUlid;

final readonly class BroadcastVerifier
{
    public function __construct(
        private BroadcastContextFactory $contextFactory,
        private BroadcastTypeRegistry $types,
    ) {
    }

    public function verify(PrefixedUlid $broadcastId): BroadcastVerifyResult
    {
        $context = $this->contextFactory->build($broadcastId);
        $handler = $this->types->handlerFor($context->broadcast->type);

        return $handler->verify($context);
    }
}
