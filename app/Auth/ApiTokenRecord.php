<?php

declare(strict_types=1);

namespace App\Auth;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'api_tokens')]
final class ApiTokenRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $userId,
        public string $name,
        public string $tokenHash,
        public ?string $tokenPreview = null,
        public ?string $scopesJson = null,
        public ?string $lastUsedAt = null,
        public ?string $expiresAt = null,
        public ?string $createdAt = null,
        public ?string $revokedAt = null,
    ) {
    }
}
