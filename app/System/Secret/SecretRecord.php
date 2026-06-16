<?php

declare(strict_types=1);

namespace App\System\Secret;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'secrets')]
final class SecretRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $key,
        public SecretType $type,
        public string $encryptedValue,
        public string $nonce,
        public ?string $metadataJson = null,
        public ?string $lastUsedAt = null,
        public ?string $revokedAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
