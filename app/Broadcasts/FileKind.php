<?php

declare(strict_types=1);

namespace App\Broadcasts;

enum FileKind: string
{
    case Video = 'video';
    case Audio = 'audio';
}
