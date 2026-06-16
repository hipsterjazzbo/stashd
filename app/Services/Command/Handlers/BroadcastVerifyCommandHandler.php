<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandType;

final readonly class BroadcastVerifyCommandHandler extends AbstractBroadcastCommandHandler
{
    public function type(): CommandType
    {
        return CommandType::BroadcastVerify;
    }

    protected function action(): string
    {
        return 'verify';
    }
}
