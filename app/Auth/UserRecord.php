<?php

declare(strict_types=1);

namespace App\Auth;

use Tempest\Auth\Authentication\Authenticatable;
use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;
use Tempest\DateTime\DateTime;

#[Table(name: 'users')]
final class UserRecord implements Authenticatable
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public string $email,
        public string $passwordHash,
        public UserRole $role,
        public string $username = '',
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
    ) {
    }
}
