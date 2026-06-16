<?php

declare(strict_types=1);

namespace App\Vault;

enum MetadataSnapshotType: string
{
    case Discovery = 'discovery';
    case MetadataCapture = 'metadata_capture';
    case MetadataRefresh = 'metadata_refresh';
    case DownloadInfo = 'download_info';
}
