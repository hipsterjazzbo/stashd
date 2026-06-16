<?php

declare(strict_types=1);

namespace App\Domain\Provider;

interface MetadataStrategyHandler
{
    public function strategyKey(): string;

    /** @return list<DiscoveredItem> */
    public function enrich(ResolvedInput $input, DiscoveredItem $item): DiscoveredItem;
}
