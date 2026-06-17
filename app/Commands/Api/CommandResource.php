<?php

declare(strict_types=1);

namespace App\Commands\Api;

use App\Commands\CommandRecord;
use App\Http\Api\ApiJson;
use App\Support\Arrayable;

final readonly class CommandResource implements Arrayable
{
    public function __construct(
        private CommandRecord $command,
    ) {
    }

    public static function fromRecord(CommandRecord $command): self
    {
        return new self($command);
    }

    public function toArray(): array
    {
        return ApiJson::encode([
            'id' => (string) $this->command->id,
            'type' => $this->command->type->value,
            'state' => $this->command->state->value,
            'targetType' => $this->command->targetType,
            'targetId' => $this->command->targetId,
            'options' => $this->decodeJson($this->command->optionsJson),
            'result' => $this->decodeJson($this->command->resultJson),
            'createdByUserId' => $this->command->createdByUserId,
            'createdAt' => $this->command->createdAt,
            'updatedAt' => $this->command->updatedAt,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? ApiJson::encode($decoded) : null;
    }
}
