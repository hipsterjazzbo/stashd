<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandType;

final readonly class BroadcastPlanCommandHandler extends AbstractBroadcastCommandHandler
{
    public function type(): CommandType
    {
        return CommandType::BroadcastPlan;
    }

    protected function action(): string
    {
        return 'plan';
    }
}
