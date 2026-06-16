<?php

declare(strict_types=1);

namespace App\Stashes;

use App\Providers\ResolvedInput;

final readonly class PreflightExecutionResult
{
    /** @param list<array<string, mixed>> $discoveredItems */
    public function __construct(
        public string $sourceUri,
        public ?string $sourceTitle,
        public PreflightOrigin $origin,
        public ResolvedInput $resolvedInput,
        public string $strategyKey,
        public int $estimatedItemCount,
        public int $estimatedTotalDurationSeconds,
        public array $discoveredItems,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function sampleItems(int $limit = 5): array
    {
        return array_slice($this->discoveredItems, 0, $limit);
    }

    /** @return array<string, mixed> */
    public function toResultArray(string $reviewUrl): array
    {
        return [
            'source_uri' => $this->sourceUri,
            'source_title' => $this->sourceTitle,
            'origin' => $this->origin->value,
            'review_url' => $reviewUrl,
            'resolved_input' => [
                'provider_key' => $this->resolvedInput->providerKey,
                'input_type' => $this->resolvedInput->inputType,
                'source_uri' => $this->resolvedInput->sourceUri->toString(),
                'provider_input_id' => $this->resolvedInput->providerInputId,
                'title' => $this->resolvedInput->title,
            ],
            'discovery' => [
                'strategy_key' => $this->strategyKey,
                'estimated_item_count' => $this->estimatedItemCount,
                'estimated_total_duration_seconds' => $this->estimatedTotalDurationSeconds,
                'discovered_items' => $this->discoveredItems,
                'sample_items' => $this->sampleItems(),
            ],
        ];
    }
}
