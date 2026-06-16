<?php

declare(strict_types=1);

namespace App\Broadcasts;

/** Atomically writes generated broadcast sidecars (NFO, etc.). */
final class BroadcastSidecarWriter
{
    public function write(string $absolutePath, string $content): void
    {
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw BroadcastException::withCode(
                'broadcast_sidecar_write_failed',
                'Could not create sidecar directory.',
            );
        }

        $tempPath = $absolutePath . '.stashd-tmp-' . bin2hex(random_bytes(4));

        if (file_put_contents($tempPath, $content) === false) {
            @unlink($tempPath);

            throw BroadcastException::withCode(
                'broadcast_sidecar_write_failed',
                'Could not write sidecar temp file.',
            );
        }

        if (! rename($tempPath, $absolutePath)) {
            @unlink($tempPath);

            throw BroadcastException::withCode(
                'broadcast_sidecar_write_failed',
                'Could not rename sidecar into final path.',
            );
        }
    }
}
