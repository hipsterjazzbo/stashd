<?php

declare(strict_types=1);

namespace App\Providers;

use App\Vault\MediaItemRecord;

final readonly class SponsorBlockProviderEligibility
{
    public function supports(MediaItemRecord $mediaItem): bool
    {
        return $mediaItem->providerKey === 'youtube';
    }
}
