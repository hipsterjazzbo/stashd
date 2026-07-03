<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Stringable;
use Tempest\Database\PrimaryKey;

final readonly class PrefixedUlid implements Stringable
{
    private const string PATTERN = '/^[a-z]+_[0-9A-HJKMNP-TV-Z]{26}$/';

    public function __construct(
        public string $prefix,
        public string $ulid,
    ) {
        if ($prefix === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $prefix)) {
            throw new InvalidArgumentException("Invalid ULID prefix: {$prefix}");
        }

        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid)) {
            throw new InvalidArgumentException("Invalid ULID body: {$ulid}");
        }
    }

    public static function parse(string $value): self
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException("Invalid prefixed ULID: {$value}");
        }

        [$prefix, $ulid] = explode('_', $value, 2);

        return new self($prefix, $ulid);
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, $value);
    }

    public function toString(): string
    {
        return "{$this->prefix}_{$this->ulid}";
    }

    public function toPrimaryKey(): PrimaryKey
    {
        return new PrimaryKey($this->toString());
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
