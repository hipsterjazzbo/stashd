<?php

declare(strict_types=1);

namespace App\Database;

use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CompoundStatement;

final class CreateSseConnectionsTable implements MigratesUp
{
    use MigrationSchemaHelpers;

    public string $name = '2026_06_22_create_sse_connections';

    public function up(): QueryStatement
    {
        return new CompoundStatement(
            ...$this->tablesWithIndexes($this->prefixedIdTable('sse_connections')),
        );
    }
}
