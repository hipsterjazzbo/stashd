<?php

declare(strict_types=1);

namespace App\Support\Ids;

use Tempest\Mapper\DynamicSerializer;
use Tempest\Mapper\Exceptions\ValueCouldNotBeSerialized;
use Tempest\Mapper\Serializer;
use Tempest\Reflection\PropertyReflector;
use Tempest\Reflection\TypeReflector;

/** Auto-discovered: serializes any PrefixedId-typed property back to its raw string column. */
final class PrefixedIdSerializer implements DynamicSerializer, Serializer
{
    public static function accepts(PropertyReflector|TypeReflector $input): bool
    {
        $type = $input instanceof PropertyReflector ? $input->getType() : $input;

        return $type->isClass() && is_a($type->getName(), PrefixedId::class, allow_string: true);
    }

    public function serialize(mixed $input): string
    {
        if (! $input instanceof PrefixedId) {
            throw new ValueCouldNotBeSerialized(PrefixedId::class);
        }

        return $input->toString();
    }
}
