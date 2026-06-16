<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

final class InodeHelper
{
    public static function sameFile(string $pathA, string $pathB): bool
    {
        clearstatcache(true, $pathA);
        clearstatcache(true, $pathB);

        if (! is_file($pathA) || ! is_file($pathB)) {
            return false;
        }

        $inodeA = @fileinode($pathA);
        $inodeB = @fileinode($pathB);

        if (! is_int($inodeA) || ! is_int($inodeB)) {
            return false;
        }

        if ($inodeA !== $inodeB) {
            return false;
        }

        $statA = @stat($pathA);
        $statB = @stat($pathB);

        if (
            is_array($statA)
            && is_array($statB)
            && isset($statA['dev'], $statB['dev'])
            && $statA['dev'] !== $statB['dev']
        ) {
            return false;
        }

        return true;
    }
}
