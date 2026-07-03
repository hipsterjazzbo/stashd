<?php

declare(strict_types=1);

namespace App\Support\Ids;

use App\Support\PrefixedUlid;
use InvalidArgumentException;
use Stringable;
use Tempest\Database\PrimaryKey;

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

    public static function fromPrimaryKey(PrimaryKey $key): static
    {
        return new static((string) $key->value);
    }

    public function toPrimaryKey(): PrimaryKey
    {
        return new PrimaryKey($this->value);
    }

    public static function isValid(string $value): bool
    {
        return PrefixedUlid::isValid($value) && PrefixedUlid::parse($value)->prefix === static::PREFIX;
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
