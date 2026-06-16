<?php

declare(strict_types=1);

namespace App\Domain\Download\Ytdlp;

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

    public bool $failNextDownload = false;

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

    public function extractInfo(string $url, string $workingDirectory): VideoInfo
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

    public function download(string $url, string $workingDirectory, Options $options): \Ytdlphp\DownloadResult
    {
        $this->downloadCalls++;
        $this->lastDownloadOptions = [$options];

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
