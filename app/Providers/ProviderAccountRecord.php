<?php

declare(strict_types=1);

namespace App\Providers;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;
use Tempest\Mapper\Hidden;

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
        #[Hidden]
        public ?string $secretId = null,
        public ?DateTime $lastCheckedAt = null,
        public ?string $lastError = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
