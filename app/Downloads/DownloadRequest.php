<?php

declare(strict_types=1);

namespace App\Downloads;

use App\Providers\StashdUri;
use App\Stashes\DownloadPolicy;
use App\Support\PrefixedUlid;

final readonly class DownloadRequest
{
    public function __construct(
        public PrefixedUlid $mediaItemId,
        public PrefixedUlid $stashId,
        public string $providerKey,
        public string $providerItemId,
        public StashdUri $canonicalUri,
        public DownloadPolicy $downloadPolicy,
        public string $tempDirectory,
        public bool $force = false,
        public ?int $durationSeconds = null,
        public ?StashdUri $thumbnailUri = null,
        public ?string $title = null,
        public ?string $publishedAt = null,
    ) {
    }
}
