<?php

declare(strict_types=1);

namespace App\Domain\Provider;

interface DiscoveryStrategyHandler
{
    public function strategyKey(): string;

    /** @return list<DiscoveredItem> */
    public function discover(ResolvedInput $input): array;
}
