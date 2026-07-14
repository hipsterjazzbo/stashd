<?php

declare(strict_types=1);

use App\Timeline\TimelineEntryCategory;
use App\Timeline\TimelineEntryRepository;
use App\Timeline\TimelineEntrySource;
use App\Timeline\YtdlpChapterCapture;
use App\Vault\AssetKind;
use App\Vault\AssetRepository;
use App\Vault\AssetRole;
use App\Vault\AssetState;
use App\Vault\MediaItemId;
use App\Vault\MediaItemRepository;

test('yt-dlp source chapters are captured once as timeline entries', function (): void {
    $mediaItems = $this->container->get(MediaItemRepository::class);
    $mediaItem = $mediaItems->create(
        providerKey: 'youtube',
        providerItemId: 'chapter-capture-' . bin2hex(random_bytes(4)),
        canonicalUri: 'https://www.youtube.com/watch?v=chapterCapture',
        title: 'Chapter capture',
    );
    $path = sys_get_temp_dir() . '/stashd-chapters-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($path, json_encode([
        'result' => [
            'extract_info' => [
                'chapters' => [
                    ['start_time' => 0, 'end_time' => 42.5, 'title' => 'Introduction'],
                    ['start_time' => 42.5, 'end_time' => 90, 'title' => 'Main topic'],
                    ['start_time' => 90, 'end_time' => 90, 'title' => 'Invalid'],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    $this->container->get(AssetRepository::class)->create(
        mediaItemId: MediaItemId::fromPrimaryKey($mediaItem->id),
        role: AssetRole::SourceJson,
        kind: AssetKind::Metadata,
        state: AssetState::Ready,
        path: $path,
    );

    $capture = $this->container->get(YtdlpChapterCapture::class);
    $entries = $this->container->get(TimelineEntryRepository::class);

    expect($capture->capture(MediaItemId::fromPrimaryKey($mediaItem->id)))->toBe(2)
        ->and($capture->capture(MediaItemId::fromPrimaryKey($mediaItem->id)))->toBe(0);

    $captured = $entries->listForMediaItem(MediaItemId::fromPrimaryKey($mediaItem->id));

    expect($captured)->toHaveCount(2)
        ->and($captured[0]->source)->toBe(TimelineEntrySource::Ytdlp)
        ->and($captured[0]->category)->toBe(TimelineEntryCategory::Chapter)
        ->and($captured[0]->title)->toBe('Introduction')
        ->and($captured[0]->startSeconds)->toBe(0.0)
        ->and($captured[1]->endSeconds)->toBe(90.0);

    unlink($path);
});
