<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Vault\AssetRecord;

final readonly class PodcastAssetSelection
{
    public function __construct(
        public AssetRecord $asset,
        public string $mimeType,
        public string $extension,
        public int $length,
    ) {
    }
}
