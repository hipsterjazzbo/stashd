<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Support\PrefixedUlidGenerator;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class SponsorBlockRefreshRepository
{
    public function __construct(private PrefixedUlidGenerator $ids)
    {
    }

    public function create(BroadcastItemId $broadcastItemId, DateTime $nextCheckAt): SponsorBlockRefreshRecord
    {
        $now = DateTime::now(Timezone::UTC);
        $record = new SponsorBlockRefreshRecord($broadcastItemId, $nextCheckAt, createdAt: $now, updatedAt: $now);
        $record->id = new PrimaryKey($this->ids->generate('sbrefresh')->toString());
        query(SponsorBlockRefreshRecord::class)->insert($record)->execute();

        return $record;
    }

    /** @return list<SponsorBlockRefreshRecord> */
    public function listDue(DateTime $now): array
    {
        return array_values(SponsorBlockRefreshRecord::select()
            ->whereNull('completedAt')
            ->where('nextCheckAt', '<=', $now)
            ->orderBy('nextCheckAt', Direction::ASC)
            ->all());
    }

    public function complete(SponsorBlockRefreshRecord $record): void
    {
        $record->completedAt = DateTime::now(Timezone::UTC);
        $record->lastCheckedAt = $record->completedAt;
        $record->lastError = null;
        $record->updatedAt = $record->completedAt;
        $record->save();
    }
}
