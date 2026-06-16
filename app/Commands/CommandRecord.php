<?php

declare(strict_types=1);

namespace App\Commands;

use Tempest\Database\IsDatabaseModel;
use Tempest\Database\PrimaryKey;
use Tempest\Database\Table;

#[Table(name: 'commands')]
final class CommandRecord
{
    use IsDatabaseModel;

    public PrimaryKey $id;

    public function __construct(
        public CommandType $type,
        public CommandState $state,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $optionsJson = null,
        public ?string $resultJson = null,
        public ?string $createdByUserId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
