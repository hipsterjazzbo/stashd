<?php

declare(strict_types=1);

namespace App\Vault;

/**
 * Referenced only by the already-shipped `CreateDomainSchema` migration's
 * `snapshotType` enum column -- the record type this described
 * (`RawMetadataSnapshotRecord`) was never instantiated and has been removed
 * (see `DropRawMetadataSnapshots`). Kept so replaying an existing migration
 * history still resolves the class; do not build new features on it.
 */
enum MetadataSnapshotType: string
{
    case Discovery = 'discovery';
    case MetadataCapture = 'metadata_capture';
    case MetadataRefresh = 'metadata_refresh';
    case DownloadInfo = 'download_info';
}
