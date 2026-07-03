<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Broadcasts\BroadcastRecord;

enum PodcastMediaKind: string
{
    case Audio = 'audio';
    case Video = 'video';

    public static function forBroadcast(BroadcastRecord $broadcast): self
    {
        $value = $broadcast->settings['media_kind'] ?? null;

        return self::tryFrom((string) $value) ?? self::Audio;
    }
}
