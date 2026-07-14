<?php

declare(strict_types=1);

namespace App\Broadcasts;

final readonly class BroadcastCreationPreview
{
    public function __construct(
        public int $eligibleItemCount,
        public int $skippedItemCount,
        public int $vaultSizeBytes,
        public int $hardlinkedItemCount,
        public int $transcodeItemCount,
        public int $derivedMediaItemCount = 0,
        public int $derivedMediaBytes = 0,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'eligibleItemCount' => $this->eligibleItemCount,
            'skippedItemCount' => $this->skippedItemCount,
            'vaultSizeBytes' => $this->vaultSizeBytes,
            'hardlinkedItemCount' => $this->hardlinkedItemCount,
            'transcodeItemCount' => $this->transcodeItemCount,
            'derivedMediaItemCount' => $this->derivedMediaItemCount,
            'derivedMediaBytes' => $this->derivedMediaBytes,
        ];
    }
}
