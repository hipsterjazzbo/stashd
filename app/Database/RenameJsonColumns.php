<?php

declare(strict_types=1);

namespace App\Database;

use Tempest\Database\MigratesUp;
use Tempest\Database\QueryStatement;
use Tempest\Database\QueryStatements\CompoundStatement;
use Tempest\Database\QueryStatements\RawStatement;

final class RenameJsonColumns implements MigratesUp
{
    public string $name = '2026_07_03_rename_json_columns';

    public function up(): QueryStatement
    {
        return new CompoundStatement(
            new RawStatement('ALTER TABLE `commands` RENAME COLUMN `optionsJson` TO `options`'),
            new RawStatement('ALTER TABLE `commands` RENAME COLUMN `resultJson` TO `result`'),
            new RawStatement('ALTER TABLE `jobs` RENAME COLUMN `payloadJson` TO `payload`'),
            new RawStatement('ALTER TABLE `broadcasts` RENAME COLUMN `settingsJson` TO `settings`'),
            new RawStatement('ALTER TABLE `broadcast_triggers` RENAME COLUMN `settingsJson` TO `settings`'),
            new RawStatement('ALTER TABLE `media_server_connections` RENAME COLUMN `settingsJson` TO `settings`'),
            new RawStatement('ALTER TABLE `activity_events` RENAME COLUMN `metadataJson` TO `metadata`'),
            new RawStatement('ALTER TABLE `secrets` RENAME COLUMN `metadataJson` TO `metadata`'),
            new RawStatement('ALTER TABLE `storage_checks` RENAME COLUMN `detailsJson` TO `details`'),
            new RawStatement('ALTER TABLE `event_notifications` RENAME COLUMN `payloadJson` TO `payload`'),
            new RawStatement('ALTER TABLE `stash_inputs` RENAME COLUMN `optionsJson` TO `options`'),
            new RawStatement('ALTER TABLE `api_tokens` RENAME COLUMN `scopesJson` TO `scopes`'),
        );
    }
}
