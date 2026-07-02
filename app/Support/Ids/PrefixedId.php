<?php

declare(strict_types=1);

namespace App\Support\Ids;

use App\Support\PrefixedUlid;
use InvalidArgumentException;
use Stringable;

/** Base for entity-specific typed IDs (UserId, StashId, ...), each fixed to one ULID prefix. */
abstract readonly class PrefixedId implements Stringable
{
    protected const string PREFIX = '';

    final public function __construct(
        public string $value,
    ) {
        $parsed = PrefixedUlid::parse($value);

        if ($parsed->prefix !== static::PREFIX) {
            throw new InvalidArgumentException(sprintf(
                'Expected a "%s_" prefixed id for %s, got: %s',
                static::PREFIX,
                static::class,
                $value,
            ));
        }
    }

    public static function parse(string $value): static
    {
        return new static($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
