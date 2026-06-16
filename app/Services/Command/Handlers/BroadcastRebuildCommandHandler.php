<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandType;

final readonly class BroadcastRebuildCommandHandler extends AbstractBroadcastCommandHandler
{
    public function type(): CommandType
    {
        return CommandType::BroadcastRebuild;
    }

    protected function action(): string
    {
        return 'rebuild';
    }
}
