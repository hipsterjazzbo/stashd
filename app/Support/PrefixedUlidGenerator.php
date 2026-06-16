<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Uid\Ulid;

final class PrefixedUlidGenerator
{
    public function generate(string $prefix): PrefixedUlid
    {
        return new PrefixedUlid($prefix, (string) new Ulid());
    }
}
