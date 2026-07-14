<?php

declare(strict_types=1);

namespace App\Database;

use App\Timeline\TimelineEntryCategory;
use App\Timeline\TimelineEntryKind;
use App\Timeline\TimelineEntrySource;
use App\Timeline\TimelineEntryState;
use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CompoundStatement;
use Tempest\Database\QueryStatements\OnDelete;

final class CreateMediaTimelineEntries implements MigratesUp
{
    use MigrationSchemaHelpers;

    public string $name = '2026_07_14_create_media_timeline_entries';

    public function up(): QueryStatement
    {
        $table = $this->prefixedIdTable('media_timeline_entries')
            ->raw($this->fkColumn('mediaItemId', 40, 'media_items', OnDelete::CASCADE))
            ->enum('source', TimelineEntrySource::class)
            ->enum('kind', TimelineEntryKind::class)
            ->enum('category', TimelineEntryCategory::class)
            ->float('startSeconds')
            ->float('endSeconds')
            ->enum('state', TimelineEntryState::class, default: TimelineEntryState::Ready)
            ->string('title', nullable: true)
            ->string('externalId', nullable: true)
            ->text('raw', nullable: true)
            ->datetime('lastCheckedAt', nullable: true)
            ->index('mediaItemId')
            ->index('source')
            ->unique('mediaItemId', 'source', 'externalId');

        return new CompoundStatement(...$this->tablesWithIndexes($table));
    }
}
