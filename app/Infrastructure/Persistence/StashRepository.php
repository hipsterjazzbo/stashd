<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Stash\DownloadPolicy;
use App\Domain\Stash\OrganizationMode;
use App\Domain\Stash\StashRecord;
use App\Domain\Stash\StashState;
use App\Domain\Stash\SyncMode;
use App\Domain\Support\PrefixedUlid;
use App\Domain\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class StashRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        string $name,
        string $slug,
        SyncMode $syncMode = SyncMode::Automatic,
        DownloadPolicy $downloadPolicy = DownloadPolicy::Video,
        OrganizationMode $organizationMode = OrganizationMode::Flat,
        ?string $description = null,
    ): StashRecord {
        $id = $this->ids->generate('stash')->toString();
        $record = new StashRecord(
            name: $name,
            slug: $slug,
            syncMode: $syncMode,
            downloadPolicy: $downloadPolicy,
            organizationMode: $organizationMode,
            state: StashState::Ready,
            description: $description,
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(StashRecord::class)->insert($record)->execute();

        return StashRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist stash record.');
    }

    public function find(PrefixedUlid $id): ?StashRecord
    {
        return StashRecord::findById(new PrimaryKey($id->toString()));
    }

    public function findBySlug(string $slug): ?StashRecord
    {
        return StashRecord::select()->where('slug = ?', $slug)->first();
    }
}
