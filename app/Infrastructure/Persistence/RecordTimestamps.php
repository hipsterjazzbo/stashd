<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class RecordTimestamps
{
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function apply(object $record): void
    {
        if (property_exists($record, 'createdAt')) {
            $record->createdAt ??= self::now();
        }

        if (property_exists($record, 'updatedAt')) {
            $record->updatedAt ??= self::now();
        }
    }
}
