<?php

declare(strict_types=1);

namespace App\Domain\Storage;

enum StorageLocationState: string
{
    case Ready = 'ready';
    case Missing = 'missing';
    case Unwritable = 'unwritable';
    case LowSpace = 'low_space';
    case Unavailable = 'unavailable';
    case Failed = 'failed';
}
