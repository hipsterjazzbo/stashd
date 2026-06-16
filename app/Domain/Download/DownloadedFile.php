<?php

declare(strict_types=1);

namespace App\Domain\Download;

use App\Domain\Media\AssetKind;
use App\Domain\Media\AssetRole;

final readonly class DownloadedFile
{
    public function __construct(
        public string $tempPath,
        public string $filename,
        public AssetRole $role,
        public AssetKind $kind,
        public ?string $mimeType = null,
        public ?string $container = null,
        public ?int $sizeBytes = null,
        public ?int $durationSeconds = null,
    ) {
    }
}
