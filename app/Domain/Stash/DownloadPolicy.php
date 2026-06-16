<?php

declare(strict_types=1);

namespace App\Domain\Stash;

enum DownloadPolicy: string
{
    case Video = 'video';
    case AudioOnly = 'audio_only';
    case MetadataOnly = 'metadata_only';
    case ManualDownload = 'manual_download';
}
