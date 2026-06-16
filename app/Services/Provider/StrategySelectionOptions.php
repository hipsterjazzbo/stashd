<?php

declare(strict_types=1);

namespace App\Services\Provider;

final readonly class StrategySelectionOptions
{
    public function __construct(
        public bool $allowLastResort = false,
    ) {
    }
}
