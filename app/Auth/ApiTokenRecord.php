<?php

declare(strict_types=1);

namespace App\Auth;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;
use Tempest\Mapper\Hidden;

#[Table(name: 'api_tokens')]
final class ApiTokenRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $userId,
        public string $name,
        #[Hidden]
        public string $tokenHash,
        public ?string $tokenPreview = null,
        public ?ApiTokenScopes $scopesJson = null,
        public ?DateTime $lastUsedAt = null,
        public ?DateTime $expiresAt = null,
        public ?DateTime $createdAt = null,
        public ?DateTime $revokedAt = null,
    ) {
    }
}
