<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use Tempest\Auth\Authentication\Authenticatable;
use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'users')]
final class UserRecord implements Authenticatable
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $email,
        public string $username,
        public string $passwordHash,
        public UserRole $role,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
