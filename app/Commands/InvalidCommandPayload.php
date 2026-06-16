<?php

declare(strict_types=1);

namespace App\Commands;

use InvalidArgumentException;

final class InvalidCommandPayload extends InvalidArgumentException
{
    /** @param list<string> $errors */
    public static function withErrors(array $errors): self
    {
        return new self(implode(' ', $errors));
    }
}
