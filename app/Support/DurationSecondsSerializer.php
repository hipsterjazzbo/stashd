<?php

declare(strict_types=1);

namespace App\Support;

use Tempest\DateTime\Duration;
use Tempest\Mapper\Serializer;

final class DurationSecondsSerializer implements Serializer
{
    public function serialize(mixed $input): int
    {
        assert($input instanceof Duration);

        return (int) $input->getTotalSeconds();
    }
}
