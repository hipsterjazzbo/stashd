<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandType;

final readonly class BroadcastPruneCommandHandler extends AbstractBroadcastCommandHandler
{
    public function type(): CommandType
    {
        return CommandType::BroadcastPrune;
    }

    protected function action(): string
    {
        return 'prune';
    }
}
