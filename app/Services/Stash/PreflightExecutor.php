<?php

declare(strict_types=1);

namespace App\Services\Stash;

use App\Domain\Provider\DiscoveredItem;
use App\Domain\Provider\ProviderRegistry;
use App\Domain\Provider\ResolvedInput;
use App\Domain\Provider\StashdUri;
use App\Domain\Provider\StrategyPurpose;
use App\Domain\Stash\PreflightOrigin;
use App\Services\Provider\ProviderStrategySelector;

use function Tempest\Support\str;

final readonly class PreflightExecutor
{
    public function __construct(
        private ProviderRegistry $providers,
        private ProviderStrategySelector $strategySelector,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function execute(array $payload): PreflightExecutionResult
    {
        $sourceUri = str((string) ($payload['source_uri'] ?? ''))->trim()->toString();
        $sourceTitle = isset($payload['source_title']) && is_string($payload['source_title']) && str($payload['source_title'])->trim()->isNotEmpty()
            ? str($payload['source_title'])->trim()->toString()
            : null;
        $origin = PreflightOrigin::tryFrom((string) ($payload['origin'] ?? '')) ?? PreflightOrigin::Api;

        $uri = StashdUri::parse($sourceUri);
        $provider = $this->providers->resolveForUri($uri);
        $resolved = $provider->resolveInput($uri);

        if ($sourceTitle !== null) {
            $resolved = new ResolvedInput(
                providerKey: $resolved->providerKey,
                inputType: $resolved->inputType,
                sourceUri: $resolved->sourceUri,
                providerInputId: $resolved->providerInputId,
                title: $sourceTitle,
            );
        }

        $strategy = $this->strategySelector->select($provider, StrategyPurpose::Discovery);
        $discovered = $provider->discover($resolved, $strategy);
        $discoveredItems = DiscoveredItemSerializer::manyToArray($discovered);

        $estimatedItemCount = count($discovered);
        $estimatedDuration = array_sum(array_map(
            static fn (DiscoveredItem $item): int => $item->durationSeconds ?? 0,
            $discovered,
        ));

        return new PreflightExecutionResult(
            sourceUri: $sourceUri,
            sourceTitle: $sourceTitle,
            origin: $origin,
            resolvedInput: $resolved,
            strategyKey: $strategy->key,
            estimatedItemCount: $estimatedItemCount,
            estimatedTotalDurationSeconds: $estimatedDuration,
            discoveredItems: $discoveredItems,
        );
    }
}
