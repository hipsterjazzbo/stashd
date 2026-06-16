<?php

declare(strict_types=1);

namespace App\System\State;

use InvalidArgumentException;

final class InvalidStateTransition extends InvalidArgumentException
{
    public static function forEntity(string $entity, string $from, string $to): self
    {
        return new self("{$entity} cannot transition from {$from} to {$to}.");
    }
}
