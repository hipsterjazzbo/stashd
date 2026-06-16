<?php

declare(strict_types=1);

namespace App\Services\Command;

use App\Domain\Command\CommandType;

final readonly class CommandHandlerRegistry
{
    /** @param list<CommandHandler> $handlers */
    public function __construct(
        private array $handlers,
    ) {
    }

    public function handlerFor(CommandType $type): CommandHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->type() === $type) {
                return $handler;
            }
        }

        throw new InvalidCommandPayload('Unsupported command type: ' . $type->value);
    }
}
