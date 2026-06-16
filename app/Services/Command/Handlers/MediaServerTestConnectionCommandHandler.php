<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandType;

final readonly class MediaServerTestConnectionCommandHandler extends AbstractMediaServerCommandHandler
{
    public function type(): CommandType
    {
        return CommandType::MediaServerTestConnection;
    }

    protected function action(): string
    {
        return 'test_connection';
    }
}
