<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Vault\AssetRecord;

final readonly class PodcastMimeType
{
    private const array AUDIO_MIME_BY_EXTENSION = [
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        'mp3' => 'audio/mpeg',
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'wav' => 'audio/wav',
    ];

    private const array VIDEO_MIME_BY_EXTENSION = [
        'm4v' => 'video/mp4',
        'mov' => 'video/quicktime',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    /** @return list<string> */
    public function supportedAudioMimeTypes(): array
    {
        return array_values(array_unique(self::AUDIO_MIME_BY_EXTENSION));
    }

    /** @return list<string> */
    public function supportedVideoMimeTypes(): array
    {
        return array_values(array_unique(self::VIDEO_MIME_BY_EXTENSION));
    }

    public function forAudioAsset(AssetRecord $asset): ?string
    {
        return $this->resolve($asset, self::AUDIO_MIME_BY_EXTENSION);
    }

    public function forVideoAsset(AssetRecord $asset): ?string
    {
        return $this->resolve($asset, self::VIDEO_MIME_BY_EXTENSION);
    }

    public function extensionForAsset(AssetRecord $asset, string $mimeType): string
    {
        $extension = $this->extensionFromPath($asset->path);

        if ($extension !== null) {
            return $extension;
        }

        return match ($mimeType) {
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            default => 'mp4',
        };
    }

    /** @param array<string, string> $allowedByExtension */
    private function resolve(AssetRecord $asset, array $allowedByExtension): ?string
    {
        if ($asset->mimeType !== null && in_array($asset->mimeType, $allowedByExtension, true)) {
            return $asset->mimeType;
        }

        $extension = $this->extensionFromPath($asset->path);

        if ($extension === null) {
            return null;
        }

        return $allowedByExtension[$extension] ?? null;
    }

    private function extensionFromPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === '' ? null : $extension;
    }
}
