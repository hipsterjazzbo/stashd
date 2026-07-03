<?php

declare(strict_types=1);

namespace App\Stashes\Api;

use App\Http\Api\ApiJson;
use App\Stashes\StashInputRecord;

final readonly class StashInputResource
{
    public function __construct(
        private StashInputRecord $input,
    ) {
    }

    public static function fromRecord(StashInputRecord $input): self
    {
        return new self($input);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        // options.provider is keyed by opaque provider option strings (e.g.
        // 'include_shorts'), not DTO field names — ApiJson::encode()'s
        // snake/camel key transform must not touch them (it would corrupt any
        // future option key containing an uppercase letter), so it's pulled
        // out before encoding and reattached verbatim. See StashInputOptions
        // and the equivalent fix in BroadcastResource::toArray().
        $options = $this->input->options?->toArray();
        $provider = is_array($options) ? ($options['provider'] ?? null) : null;

        $encoded = ApiJson::encode([
            'id' => (string) $this->input->id,
            'stashId' => (string) $this->input->stashId,
            'providerKey' => $this->input->providerKey,
            'inputType' => $this->input->inputType->value,
            'sourceUri' => $this->input->sourceUri,
            'providerInputId' => $this->input->providerInputId,
            'state' => $this->input->state->value,
            'consecutiveFailures' => $this->input->consecutiveFailures,
            'title' => $this->input->title,
            'syncMode' => $this->input->syncMode?->value,
            'options' => $options,
            'lastCheckedAt' => $this->input->lastCheckedAt,
            'nextCheckAt' => $this->input->nextCheckAt,
            'lastSuccessAt' => $this->input->lastSuccessAt,
            'lastFailureAt' => $this->input->lastFailureAt,
            'createdAt' => $this->input->createdAt,
            'updatedAt' => $this->input->updatedAt,
        ]);

        if (is_array($provider) && is_array($encoded['options'] ?? null)) {
            $encoded['options']['provider'] = $provider;
        }

        return $encoded;
    }
}
