<?php

declare(strict_types=1);

namespace App\Broadcasts;

use App\Timeline\TimelineMetadataRenderer;
use App\Transcoding\Ffmpeg\FfmpegGateway;
use App\Vault\MediaItemId;

use function Tempest\Support\Filesystem\create_directory;

final readonly class BroadcastChapterRemuxer
{
    public function __construct(
        private FfmpegGateway $ffmpeg,
        private TimelineMetadataRenderer $timeline,
    ) {
    }

    public function remux(MediaItemId $mediaItemId, string $sourcePath, string $destinationPath): bool
    {
        $metadata = $this->timeline->render($mediaItemId);

        if ($metadata === null) {
            return false;
        }

        $extension = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['mkv', 'mp3', 'mp4', 'm4v', 'webm'], true)) {
            throw BroadcastException::withCode('broadcast_chapters_unsupported', 'The broadcast container cannot carry chapters.');
        }

        $probe = $this->ffmpeg->probe();

        if (! $probe->available) {
            throw BroadcastException::withCode('ffmpeg_unavailable', $probe->message ?? 'ffmpeg is unavailable.');
        }

        create_directory(dirname($destinationPath), 0o775);
        $temporaryPath = $destinationPath . '.remux.' . $extension;

        try {
            $this->ffmpeg->remuxWithChapters($sourcePath, $temporaryPath, $metadata);

            if (! is_file($temporaryPath) || ! @rename($temporaryPath, $destinationPath)) {
                throw BroadcastException::withCode('broadcast_remux_failed', 'Could not publish the chapter remux.');
            }
        } catch (BroadcastException $exception) {
            @unlink($temporaryPath);

            throw $exception;
        } catch (\Throwable $exception) {
            @unlink($temporaryPath);

            throw BroadcastException::withCode('broadcast_chapters_unsupported', 'Could not write chapters to the broadcast container.', $exception);
        }

        return true;
    }
}
