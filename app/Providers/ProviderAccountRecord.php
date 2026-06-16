<?php

declare(strict_types=1);

namespace App\Providers;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'provider_accounts')]
final class ProviderAccountRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $providerKey,
        public string $name,
        public ProviderAuthType $authType,
        public ProviderAccountState $state,
        public ?string $secretId = null,
        public ?string $lastCheckedAt = null,
        public ?string $lastError = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
