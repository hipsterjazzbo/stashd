<?php

declare(strict_types=1);

namespace App\Downloads;

use App\Stashes\DownloadPolicy;

final class DownloadPolicyEvaluator
{
    public function assertExplicitDownloadAllowed(DownloadPolicy $policy): void
    {
        if ($policy === DownloadPolicy::MetadataOnly) {
            throw DownloadException::withCode(
                'download_policy_metadata_only',
                'This stash is configured for metadata-only and does not download media.',
            );
        }
    }

    public function allowsAutomaticDownload(DownloadPolicy $policy): bool
    {
        return match ($policy) {
            DownloadPolicy::Video, DownloadPolicy::AudioOnly => true,
            DownloadPolicy::MetadataOnly, DownloadPolicy::ManualDownload => false,
        };
    }

    /** @return list<string> */
    public function warningsForExplicitDownload(DownloadPolicy $policy): array
    {
        if ($policy === DownloadPolicy::ManualDownload) {
            return ['Stash download policy is manual_download; this download was explicitly requested.'];
        }

        return [];
    }
}
