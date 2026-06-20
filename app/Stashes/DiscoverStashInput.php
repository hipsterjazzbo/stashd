<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Providers\Core\DiscoveredItem;
use App\Providers\ProviderRegistry;
use App\Providers\ProviderStrategySelector;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;
use App\Providers\StrategyPurpose;

use function Tempest\Support\str;

final readonly class DiscoverStashInput
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
                sourceTitle: $resolved->sourceTitle,
                sourceAvatarUri: $resolved->sourceAvatarUri,
                estimatedItemCount: $resolved->estimatedItemCount,
            );
        }

        $strategy = $this->strategySelector->select($provider, StrategyPurpose::Discovery);
        $discovered = $provider->discover($resolved, $strategy);
        $discoveredItems = DiscoveredItem::manyToArray($discovered);

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
