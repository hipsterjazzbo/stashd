<?php

declare(strict_types=1);

namespace App\Vault;

enum AssetKind: string
{
    case Video = 'video';
    case Audio = 'audio';
    case Image = 'image';
    case Subtitle = 'subtitle';
    case Metadata = 'metadata';
    case Link = 'link';
    case Feed = 'feed';
    case Other = 'other';
}
