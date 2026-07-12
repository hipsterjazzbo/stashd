<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

/** Plugin-owned settings stored in BroadcastRecord.settings. */
final readonly class PodcastFeedSettings
{
    /** @param array<string, mixed> $settings */
    public static function fromArray(array $settings): self
    {
        return new self(
            title: self::string($settings, 'title'),
            description: self::string($settings, 'description'),
            linkUrl: self::string($settings, 'link_url'),
            author: self::string($settings, 'author'),
            imageUrl: self::string($settings, 'image_url'),
            fundingUrl: self::string($settings, 'funding_url'),
            language: self::string($settings, 'language') ?? 'en',
            explicit: self::boolean($settings, 'explicit'),
            complete: self::boolean($settings, 'complete'),
            captions: self::string($settings, 'captions') ?? 'off',
            captionLanguages: self::string($settings, 'caption_languages') ?? 'en',
        );
    }

    private function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $linkUrl,
        public ?string $author,
        public ?string $imageUrl,
        public ?string $fundingUrl,
        public string $language,
        public bool $explicit,
        public bool $complete,
        public string $captions,
        public string $captionLanguages,
    ) {
    }

    /** @param array<string, mixed> $settings */
    private static function string(array $settings, string $key): ?string
    {
        $value = $settings[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $settings */
    private static function boolean(array $settings, string $key): bool
    {
        return $settings[$key] === true || $settings[$key] === 'true';
    }
}
