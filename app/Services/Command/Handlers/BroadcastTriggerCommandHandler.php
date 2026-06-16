<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandType;

final readonly class BroadcastTriggerCommandHandler extends AbstractBroadcastCommandHandler
{
    public function type(): CommandType
    {
        return CommandType::BroadcastTrigger;
    }

    protected function action(): string
    {
        return 'trigger';
    }
}
