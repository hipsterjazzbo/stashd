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
        if ($broadcast->settingsJson === null) {
            return self::Audio;
        }

        $decoded = json_decode($broadcast->settingsJson, true);
        $value = is_array($decoded) ? ($decoded['media_kind'] ?? null) : null;

        return self::tryFrom((string) $value) ?? self::Audio;
    }
}
