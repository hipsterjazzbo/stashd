<?php

declare(strict_types=1);

namespace App\Commands;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use App\Support\RecordTimestamps;
use InvalidArgumentException;
use Tempest\Database\Direction;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

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
        ?PrefixedUlid $createdByUserId = null,
    ): CommandRecord {
        $id = $this->ids->generate('cmd')->toString();
        $record = new CommandRecord(
            type: $type,
            state: CommandState::Accepted,
            targetType: $targetType,
            targetId: $targetId?->toString(),
            optionsJson: $options === null ? null : json_encode($options, JSON_THROW_ON_ERROR),
            createdByUserId: $createdByUserId?->toString(),
        );
        $record->id = new PrimaryKey($id);
        RecordTimestamps::apply($record);

        query(CommandRecord::class)->insert($record)->execute();

        return CommandRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist command record.');
    }

    public function find(PrefixedUlid $id): ?CommandRecord
    {
        return CommandRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(CommandRecord $record): CommandRecord
    {
        $record->updatedAt = RecordTimestamps::now();
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
