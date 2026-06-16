<?php

declare(strict_types=1);

namespace App\Services\Command\Handlers;

use App\Domain\Command\CommandType;

final readonly class MediaServerListLibrariesCommandHandler extends AbstractMediaServerCommandHandler
{
    public function type(): CommandType
    {
        return CommandType::MediaServerListLibraries;
    }

    protected function action(): string
    {
        return 'list_libraries';
    }
}
