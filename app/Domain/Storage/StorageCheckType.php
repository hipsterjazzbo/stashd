<?php

declare(strict_types=1);

namespace App\Domain\Storage;

enum StorageCheckType: string
{
    case Writable = 'writable';
    case Hardlink = 'hardlink';
    case Symlink = 'symlink';
    case FreeSpace = 'free_space';
    case Filesystem = 'filesystem';
}
