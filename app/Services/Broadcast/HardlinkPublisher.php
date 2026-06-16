<?php

declare(strict_types=1);

namespace App\Services\Broadcast;

use App\Domain\Broadcast\BroadcastException;
use App\Services\Storage\FilesystemProbe;
use App\Services\Storage\StorageCapabilityChecker;

/** Hardlink-first publisher — never silently copies. */
final readonly class HardlinkPublisher
{
    public function __construct(
        private StorageCapabilityChecker $storageChecker,
        private FilesystemProbe $probe,
    ) {
    }

    public function assertHardlinksAvailable(): void
    {
        if (getenv('STASHD_BROADCAST_HARDLINK_FORCE_FAIL') === '1') {
            throw BroadcastException::withCode(
                'broadcast_hardlink_unavailable',
                'Hardlinks are unavailable for broadcast publishing.',
            );
        }

        $result = $this->storageChecker->checkVaultBroadcastHardlink();

        if (! $result->ok) {
            throw BroadcastException::withCode(
                'broadcast_hardlink_unavailable',
                $result->message,
            );
        }
    }

    public function publishHardlink(string $sourcePath, string $targetPath): void
    {
        $this->assertHardlinksAvailable();

        if (! is_file($sourcePath)) {
            throw BroadcastException::withCode(
                'broadcast_source_missing',
                'Vault source file is missing.',
            );
        }

        $directory = dirname($targetPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw BroadcastException::withCode(
                'broadcast_publish_failed',
                'Could not create broadcast directory.',
            );
        }

        if (is_file($targetPath)) {
            if (InodeHelper::sameFile($sourcePath, $targetPath)) {
                return;
            }

            if (! unlink($targetPath)) {
                throw BroadcastException::withCode(
                    'broadcast_publish_failed',
                    'Existing broadcast file is not a hardlink and could not be replaced.',
                );
            }
        }

        clearstatcache(true, $sourcePath);
        clearstatcache(true, $targetPath);

        if (! @link($sourcePath, $targetPath)) {
            $error = error_get_last()['message'] ?? 'link() returned false';

            throw BroadcastException::withCode(
                'broadcast_hardlink_unavailable',
                'Hardlink creation failed: ' . $error,
            );
        }

        if (! InodeHelper::sameFile($sourcePath, $targetPath)) {
            @unlink($targetPath);

            throw BroadcastException::withCode(
                'broadcast_hardlink_unavailable',
                'Hardlink was created but inode verification failed.',
            );
        }
    }

    public function verifyHardlink(string $sourcePath, string $targetPath): bool
    {
        if (! is_file($targetPath)) {
            return false;
        }

        if (! is_file($sourcePath)) {
            return false;
        }

        return InodeHelper::sameFile($sourcePath, $targetPath);
    }
}
