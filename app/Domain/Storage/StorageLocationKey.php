<?php

declare(strict_types=1);

namespace App\Domain\Storage;

enum StorageLocationKey: string
{
    case Data = 'data';
    case Vault = 'vault';
    case Broadcasts = 'broadcasts';
    case Temp = 'temp';
    case Cache = 'cache';
}
