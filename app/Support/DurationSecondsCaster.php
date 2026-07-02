<?php

declare(strict_types=1);

namespace App\Support;

use Tempest\DateTime\Duration;
use Tempest\Mapper\Caster;

final class DurationSecondsCaster implements Caster
{
    public function cast(mixed $input): Duration
    {
        return Duration::seconds(is_numeric($input) ? (int) $input : 0);
    }
}
