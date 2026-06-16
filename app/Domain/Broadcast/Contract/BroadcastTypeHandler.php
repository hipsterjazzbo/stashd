<?php

declare(strict_types=1);

namespace App\Domain\Broadcast\Contract;

use App\Domain\Broadcast\BroadcastContext;
use App\Domain\Broadcast\BroadcastPlan;
use App\Domain\Broadcast\BroadcastPruneResult;
use App\Domain\Broadcast\BroadcastPublishResult;
use App\Domain\Broadcast\BroadcastVerifyResult;

interface BroadcastTypeHandler
{
    public function key(): string;

    public function plan(BroadcastContext $context): BroadcastPlan;

    public function publish(BroadcastContext $context, BroadcastPlan $plan): BroadcastPublishResult;

    public function verify(BroadcastContext $context): BroadcastVerifyResult;

    public function prune(BroadcastContext $context): BroadcastPruneResult;
}
