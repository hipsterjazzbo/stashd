<?php

declare(strict_types=1);

namespace App\MediaServers;

use App\Support\PrefixedUlid;
use App\Support\PrefixedUlidGenerator;
use InvalidArgumentException;
use Tempest\Database\PrimaryKey;

use function Tempest\Database\query;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

final class MediaServerConnectionRepository
{
    public function __construct(
        private PrefixedUlidGenerator $ids,
    ) {
    }

    public function create(
        MediaServerType $type,
        string $name,
        string $baseUri,
        MediaServerConnectionState $state = MediaServerConnectionState::Ready,
        ?array $settings = null,
        ?string $tokenSecretId = null,
    ): MediaServerConnectionRecord {
        $id = $this->ids->generate('mserver')->toString();
        $record = new MediaServerConnectionRecord(
            type: $type,
            name: $name,
            baseUri: $baseUri,
            state: $state,
            tokenSecretId: $tokenSecretId,
            settingsJson: MediaServerLibrarySelection::fromArray($settings),
        );
        $record->id = new PrimaryKey($id);
        $now = DateTime::now(Timezone::UTC);
        $record->createdAt ??= $now;
        $record->updatedAt ??= $now;

        query(MediaServerConnectionRecord::class)->insert($record)->execute();

        return MediaServerConnectionRecord::findById(new PrimaryKey($id))
            ?? throw new InvalidArgumentException('Failed to persist media server connection.');
    }

    public function find(PrefixedUlid $id): ?MediaServerConnectionRecord
    {
        return MediaServerConnectionRecord::findById(new PrimaryKey($id->toString()));
    }

    public function save(MediaServerConnectionRecord $record): MediaServerConnectionRecord
    {
        $record->updatedAt = DateTime::now(Timezone::UTC);
        $record->save();

        return $record;
    }

    /** @return list<MediaServerConnectionRecord> */
    public function listAll(): array
    {
        return MediaServerConnectionRecord::select()
            ->orderBy('createdAt', \Tempest\Database\Direction::ASC)
            ->all();
    }

    public function delete(MediaServerConnectionRecord $record): void
    {
        $record->delete();
    }
}
