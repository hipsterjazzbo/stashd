<?php

declare(strict_types=1);

namespace App\Services\Boot;

use RuntimeException;
use Tempest\Database\Config\SQLiteConfig;
use Tempest\Database\Database;
use Tempest\Database\Query;

/**
 * Applies SQLite pragmas on Tempest's active database connection.
 *
 * Do not open a separate PDO here: a throwaway connection (especially with
 * :memory:) would not affect the connection used by migrations and models.
 */
final readonly class SqliteConfigurator
{
    public function __construct(
        private Database $database,
    ) {
    }

    public function configure(SQLiteConfig $sqliteConfig): void
    {
        if ($sqliteConfig->path !== ':memory:') {
            $directory = dirname($sqliteConfig->path);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Stashd cannot create database directory: {$directory}");
            }
        }

        $this->database->execute(new Query('PRAGMA foreign_keys = ON'));
        $this->database->execute(new Query('PRAGMA journal_mode = WAL'));
        $this->database->execute(new Query('PRAGMA busy_timeout = 5000'));
    }

    /** @return array{foreign_keys: int|string, journal_mode: string, busy_timeout: int|string} */
    public function readPragmas(): array
    {
        return [
            'foreign_keys' => $this->fetchPragma('foreign_keys'),
            'journal_mode' => strtolower((string) $this->fetchPragma('journal_mode')),
            'busy_timeout' => $this->fetchPragma('busy_timeout'),
        ];
    }

    private function fetchPragma(string $name): int|string
    {
        $row = $this->database->fetchFirst(new Query("PRAGMA {$name}"));

        if (! is_array($row)) {
            return '';
        }

        return array_values($row)[0] ?? '';
    }
}
