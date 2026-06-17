<?php

declare(strict_types=1);

namespace App\Support;

interface Arrayable
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
