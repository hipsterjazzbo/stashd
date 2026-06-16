<?php

declare(strict_types=1);

namespace App\Domain\Storage;

enum StorageCheckState: string
{
    case Ready = 'ready';
    case Failed = 'failed';
    case Warning = 'warning';
}
