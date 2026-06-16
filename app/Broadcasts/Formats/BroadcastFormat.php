<?php

declare(strict_types=1);

namespace App\Broadcasts\Formats;

use App\Broadcasts\BroadcastContext;
use App\Broadcasts\BroadcastPlan;
use App\Broadcasts\BroadcastPruneResult;
use App\Broadcasts\BroadcastPublishResult;
use App\Broadcasts\BroadcastVerifyResult;

interface BroadcastFormat
{
    public function key(): string;

    public function plan(BroadcastContext $context): BroadcastPlan;

    public function publish(BroadcastContext $context, BroadcastPlan $plan): BroadcastPublishResult;

    public function verify(BroadcastContext $context): BroadcastVerifyResult;

    public function prune(BroadcastContext $context): BroadcastPruneResult;
}
