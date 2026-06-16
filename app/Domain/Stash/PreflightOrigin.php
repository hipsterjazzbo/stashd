<?php

declare(strict_types=1);

namespace App\Domain\Stash;

enum PreflightOrigin: string
{
    case CreateStash = 'create_stash';
    case ManualPaste = 'manual_paste';
    case Api = 'api';
    case BrowserExtension = 'browser_extension';
    case Scheduler = 'scheduler';
}
