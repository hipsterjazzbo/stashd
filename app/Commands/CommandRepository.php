<?php

declare(strict_types=1);

namespace App\Commands;

use App\Auth\UserId;
use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class CommandRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        CommandType $type,
        ?string $targetType = null,
        ?PrefixedUlid $targetId = null,
        ?array $options = null,
        ?UserId $createdByUserId = null,
    ): CommandRecord {
        $id = $this->ids->generate('cmd')->toString();
        $record = new CommandRecord(
            type: $type,
            state: CommandState::Accepted,
            targetType: $targetType,
            targetId: $targetId?->toString(),
            optionsJson: $options === null ? null : json_encode($options, JSON_THROW_ON_ERROR),
            createdByUserId: $createdByUserId,
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(CommandRecord::class)->insert($record)->execute();

        return CommandRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist command record.');
    }

    public function find(CommandId $id): ?CommandRecord
    {
        return CommandRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(CommandRecord $record): CommandRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    /** @return list<CommandRecord> */
    public function listRecent(int $limit = 50): array
    {
        return CommandRecord::select()
            ->orderBy('createdAt', Direction::DESC)
            ->limit($limit)
            ->all();
    }
}
