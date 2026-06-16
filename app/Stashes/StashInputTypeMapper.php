<?php

declare(strict_types=1);

namespace App\Stashes;

final class StashInputTypeMapper
{
    public static function fromProviderInputType(string $inputType): StashInputType
    {
        return match ($inputType) {
            'channel' => StashInputType::Channel,
            'playlist' => StashInputType::Playlist,
            'url_list' => StashInputType::UrlList,
            'video' => StashInputType::Video,
            default => StashInputType::Video,
        };
    }
}
