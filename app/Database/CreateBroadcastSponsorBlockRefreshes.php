<?php

declare(strict_types=1);

namespace App\Database;

use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CompoundStatement;
use Tempest\Database\QueryStatements\OnDelete;

final class CreateBroadcastSponsorBlockRefreshes implements MigratesUp
{
    use MigrationSchemaHelpers;

    public string $name = '2026_07_14_create_broadcast_sponsorblock_refreshes';

    public function up(): QueryStatement
    {
        $table = $this->prefixedIdTable('broadcast_sponsorblock_refreshes')
            ->raw($this->fkColumn('broadcastItemId', 40, 'broadcast_items', OnDelete::CASCADE))
            ->datetime('nextCheckAt')
            ->datetime('expiresAt')
            ->datetime('lastCheckedAt', nullable: true)
            ->datetime('completedAt', nullable: true)
            ->text('lastError', nullable: true)
            ->index('nextCheckAt')
            ->index('completedAt')
            ->unique('broadcastItemId');

        return new CompoundStatement(...$this->tablesWithIndexes($table));
    }
}
