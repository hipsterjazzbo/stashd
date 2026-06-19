<?php

declare(strict_types=1);

namespace App\Stashes\Api;

use App\Http\Api\ApiJson;
use App\Stashes\StashInputRecord;
use App\Support\Arrayable;

final readonly class StashInputResource implements Arrayable
{
    public function __construct(
        private StashInputRecord $input,
    ) {
    }

    public static function fromRecord(StashInputRecord $input): self
    {
        return new self($input);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->input->id,
            'stashId' => $this->input->stashId,
            'providerKey' => $this->input->providerKey,
            'inputType' => $this->input->inputType->value,
            'sourceUri' => $this->input->sourceUri,
            'providerInputId' => $this->input->providerInputId,
            'state' => $this->input->state->value,
            'consecutiveFailures' => $this->input->consecutiveFailures,
            'title' => $this->input->title,
            'syncMode' => $this->input->syncMode?->value,
            'lastCheckedAt' => $this->input->lastCheckedAt,
            'nextCheckAt' => $this->input->nextCheckAt,
            'lastSuccessAt' => $this->input->lastSuccessAt,
            'lastFailureAt' => $this->input->lastFailureAt,
            'createdAt' => $this->input->createdAt,
            'updatedAt' => $this->input->updatedAt,
        ]);
    }
}
