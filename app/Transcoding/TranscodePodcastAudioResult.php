<?php

declare(strict_types=1);

namespace App\Transcoding;

final readonly class TranscodePodcastAudioResult
{
    public function __construct(
        public string $mediaItemId,
        public string $assetId,
        public ?int $sizeBytes,
        public ?int $durationSeconds,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'media_item_id' => $this->mediaItemId,
            'asset_id' => $this->assetId,
            'size_bytes' => $this->sizeBytes,
            'duration_seconds' => $this->durationSeconds,
        ];
    }
}
