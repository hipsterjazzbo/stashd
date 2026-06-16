<?php

declare(strict_types=1);

namespace App\Domain\Provider;

final readonly class ResolvedInput
{
    public function __construct(
        public string $providerKey,
        public string $inputType,
        public StashdUri $sourceUri,
        public string $providerInputId,
        public ?string $title = null,
    ) {
    }
}
