<?php

declare(strict_types=1);

namespace App\Downloads\Ytdlp;

use Tempest\Process\ProcessResult;
use Ytdlphp\Metadata\VideoInfo;
use Ytdlphp\Options;

/**
 * Deterministic ytdlphp stand-in for tests — never performs network I/O.
 */
final class StubYtdlpGateway implements YtdlpGateway
{
    public int $downloadCalls = 0;

    public int $extractInfoCalls = 0;

    public int $extractPlaylistCalls = 0;

    public bool $failNextDownload = false;

    /** @var list<array<string, mixed>> */
    public array $flatPlaylistEntries = [
        ['id' => 'stub-flat-1', 'title' => 'Stub Flat Video 1', 'ie_key' => 'Youtube'],
        ['id' => 'stub-flat-2', 'title' => 'Stub Flat Video 2', 'ie_key' => 'Youtube'],
    ];

    /** @var list<Options> */
    public array $lastDownloadOptions = [];

    public function probe(): YtdlpProbeResult
    {
        return new YtdlpProbeResult(
            available: true,
            binary: 'stub-yt-dlp',
            version: 'stub-2026.06.16',
        );
    }

    public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
    {
        $this->extractInfoCalls++;

        return new VideoInfo(
            id: 'stub-video-id',
            title: 'Stub Video',
            duration: 120.0,
            ext: 'mp4',
            raw: [
                'id' => 'stub-video-id',
                'title' => 'Stub Video',
                'webpage_url' => $url,
                'duration' => 120,
                'ext' => 'mp4',
            ],
        );
    }

    public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
    {
        $this->extractPlaylistCalls++;

        return new VideoInfo(
            id: 'stub-playlist-id',
            title: 'Stub Playlist',
            raw: [
                'id' => 'stub-playlist-id',
                'title' => 'Stub Playlist',
                'webpage_url' => $url,
                'entries' => $this->flatPlaylistEntries,
            ],
        );
    }

    public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
    {
        $this->downloadCalls++;
        $this->lastDownloadOptions = [$options];

        if ($onProgress !== null) {
            $onProgress(new \Ytdlphp\DownloadProgress(downloadedBytes: 0, totalBytes: 100, percent: 0.0, etaSeconds: 2, speedBytesPerSecond: 50.0));
            $onProgress(new \Ytdlphp\DownloadProgress(downloadedBytes: 50, totalBytes: 100, percent: 50.0, etaSeconds: 1, speedBytesPerSecond: 50.0));
            $onProgress(new \Ytdlphp\DownloadProgress(downloadedBytes: 100, totalBytes: 100, percent: 100.0, etaSeconds: 0, speedBytesPerSecond: null));
        }

        if ($this->failNextDownload) {
            $this->failNextDownload = false;

            throw new \Ytdlphp\Exception\ProcessFailedException(
                new ProcessResult(1, '', 'stub download failed'),
                new \Tempest\Process\PendingProcess(['stub-yt-dlp']),
            );
        }

        $path = rtrim($workingDirectory, '/') . '/stashd-original.mp4';
        file_put_contents($path, "stub-ytdlp-media\nurl={$url}\n");

        return new \Ytdlphp\DownloadResult(
            new ProcessResult(
                exitCode: 0,
                output: "Destination: {$path}\n",
                errorOutput: '',
            ),
        );
    }
}
