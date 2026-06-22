<?php

declare(strict_types=1);

namespace App\Stashes\Api;

use App\Http\Api\ApiJson;
use App\Stashes\StashRecord;
use App\Support\Arrayable;

final readonly class StashResource implements Arrayable
{
    public function __construct(
        private StashRecord $stash,
    ) {
    }

    public static function fromRecord(StashRecord $stash): self
    {
        return new self($stash);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->stash->id,
            'name' => $this->stash->name,
            'slug' => $this->stash->slug,
            'description' => $this->stash->description,
            'syncMode' => $this->stash->syncMode->value,
            'downloadPolicy' => $this->stash->downloadPolicy->value,
            'organizationMode' => $this->stash->organizationMode->value,
            'state' => $this->stash->state->value,
            'iconUri' => $this->stash->iconUri,
            'createdAt' => $this->stash->createdAt,
            'updatedAt' => $this->stash->updatedAt,
        ]);
    }
}
