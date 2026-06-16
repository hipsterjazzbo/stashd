<?php

declare(strict_types=1);

namespace App\Vault;

/** SHA-256 checksum helper for Vault assets. Stored format: `sha256:{hex}`. */
final class VaultChecksum
{
    public const string ALGORITHM = 'sha256';

    public static function computeFile(string $path): ?string
    {
        $hash = hash_file(self::ALGORITHM, $path);

        if ($hash === false) {
            return null;
        }

        return self::format($hash);
    }

    public static function format(string $hexDigest): string
    {
        return self::ALGORITHM . ':' . strtolower($hexDigest);
    }

    public static function verifyFile(string $path, ?string $storedChecksum): bool
    {
        if ($storedChecksum === null || $storedChecksum === '') {
            return true;
        }

        $computed = self::computeFile($path);

        if ($computed === null) {
            return false;
        }

        return hash_equals(strtolower($storedChecksum), strtolower($computed));
    }
}
