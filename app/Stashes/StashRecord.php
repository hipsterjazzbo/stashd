<?php

declare(strict_types=1);

namespace App\Stashes;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'stashes')]
final class StashRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $name,
        public string $slug,
        public SyncMode $syncMode,
        public DownloadPolicy $downloadPolicy,
        public OrganizationMode $organizationMode,
        public StashState $state,
        public ?string $description = null,
        public ?string $videoQualityProfileId = null,
        public ?string $audioQualityProfileId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
