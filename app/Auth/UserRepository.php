<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\PrefixedUlidGenerator;
use App\Support\RecordTimestamps;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

final class UserRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function count(): int
    {
        return UserRecord::count()->execute();
    }

    public function findByEmail(string $email): ?UserRecord
    {
        return UserRecord::select()->where('email = ?', $email)->first();
    }

    public function findById(string $id): ?UserRecord
    {
        return UserRecord::findById(new PrimaryKey($id));
    }

    public function createOwner(string $email, string $username, string $passwordHash): UserRecord
    {
        if ($this->count() > 0) {
            throw new InvalidArgumentException('Owner account already exists.');
        }

        $id = $this->ids->generate('user')->toString();
        $record = new UserRecord(
            email: $email,
            username: $username,
            passwordHash: $passwordHash,
            role: UserRole::Owner,
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(UserRecord::class)->insert($record)->execute();

        return UserRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist user record.');
    }
}
