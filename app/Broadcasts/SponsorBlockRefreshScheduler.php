<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Providers\SponsorBlockProviderEligibility;
use App\Vault\MediaItemRecord;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final readonly class SponsorBlockRefreshScheduler
{
    private const int REFRESH_WINDOW_DAYS = 7;

    public function __construct(
        private SponsorBlockRefreshRepository $refreshes,
        private SponsorBlockProviderEligibility $providers,
    ) {
    }

    public function schedule(BroadcastRecord $broadcast, BroadcastItemRecord $item, MediaItemRecord $mediaItem): void
    {
        $settings = SponsorBlockSettings::fromBroadcastSettings($broadcast->settings ?? []);

        if (! $settings->enabled || ! $this->providers->supports($mediaItem) || $this->refreshes->findForBroadcastItem(BroadcastItemId::fromPrimaryKey($item->id)) !== null) {
            return;
        }

        $now = DateTime::now(Timezone::UTC);
        $this->refreshes->create(BroadcastItemId::fromPrimaryKey($item->id), $now, $now->plusDays(self::REFRESH_WINDOW_DAYS));
    }
}
