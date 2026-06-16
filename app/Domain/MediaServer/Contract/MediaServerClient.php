<?php

declare(strict_types=1);

namespace App\Domain\MediaServer\Contract;

use App\Domain\MediaServer\MediaServerConnectionRecord;
use App\Domain\MediaServer\MediaServerLibraryRef;
use App\Domain\MediaServer\MediaServerStatus;
use App\Domain\MediaServer\MediaServerTriggerResult;

interface MediaServerClient
{
    public function testConnection(MediaServerConnectionRecord $connection, string $token): MediaServerStatus;

    /** @return list<MediaServerLibraryRef> */
    public function listLibraries(MediaServerConnectionRecord $connection, string $token): array;

    public function triggerScan(
        MediaServerConnectionRecord $connection,
        string $token,
        MediaServerLibraryRef $library,
        ?string $path = null,
    ): MediaServerTriggerResult;
}
