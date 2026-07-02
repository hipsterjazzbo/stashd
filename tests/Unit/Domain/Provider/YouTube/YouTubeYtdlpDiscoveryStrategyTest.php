<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Provider\YouTube;

use App\Config\YtdlpConfig;
use App\Downloads\Ytdlp\YtdlpGateway;
use App\Downloads\Ytdlp\YtdlpOptionsBuilder;
use App\Downloads\Ytdlp\YtdlpProbeResult;
use App\Providers\ProviderException;
use App\Providers\ProviderHttpClient;
use App\Providers\ResolvedInput;
use App\Providers\StashdUri;
use App\Providers\YouTube\YouTubeChannelIdResolver;
use App\Providers\YouTube\YouTubeYtdlpDiscoveryStrategy;
use Ytdlphp\Metadata\VideoInfo;
use Ytdlphp\Options;

function ytdlpDiscoveryConfig(bool $realDownloads = true): YtdlpConfig
{
    return new YtdlpConfig(
        binary: 'stub-yt-dlp',
        timeoutSeconds: 60,
        realDownloadsEnabledDefault: $realDownloads,
        videoFormatSelector: 'best',
        audioFormat: 'mp3',
        audioQualityKbps: 128,
    );
}

/** Fails the test if the discovery strategy ever calls out over HTTP for an already-canonical channel id. */
function unreachableProviderHttpClient(): ProviderHttpClient
{
    return new class () implements ProviderHttpClient {
        public function get(\Stringable|string $url): \App\Providers\ProviderHttpResponse
        {
            throw new \RuntimeException('Unexpected HTTP call: ' . $url);
        }
    };
}

function ytdlpDiscoveryStrategy(YtdlpGateway $gateway, bool $realDownloads = true): YouTubeYtdlpDiscoveryStrategy
{
    $config = ytdlpDiscoveryConfig($realDownloads);

    return new YouTubeYtdlpDiscoveryStrategy(
        config: $config,
        gateway: $gateway,
        options: new YtdlpOptionsBuilder($config),
        channelIds: new YouTubeChannelIdResolver(unreachableProviderHttpClient()),
    );
}

test('ytdlp discovery is unavailable when real downloads are disabled', function (): void {
    $strategy = ytdlpDiscoveryStrategy(new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new \RuntimeException('not called');
        }
    }, realDownloads: false);

    expect($strategy->isAvailable())->toBeFalse();

    expect(fn () => $strategy->discover(new ResolvedInput(
        providerKey: 'youtube',
        inputType: 'channel',
        sourceUri: StashdUri::parse('https://www.youtube.com/channel/UCStashdDemoCh0012345678'),
        providerInputId: 'UCStashdDemoCh0012345678',
    )))->toThrow(ProviderException::class);
});

test('ytdlp discovery maps flat-playlist entries into discovered items', function (): void {
    $capturedUrl = null;
    $capturedOptions = null;

    $gateway = new class ($capturedUrl, $capturedOptions) implements YtdlpGateway {
        public function __construct(
            private mixed &$capturedUrl,
            private mixed &$capturedOptions,
        ) {
        }

        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            $this->capturedUrl = $url;
            $this->capturedOptions = $options;

            return new VideoInfo(
                id: 'stub-channel',
                title: 'Stub Channel Videos',
                raw: [
                    'entries' => [
                        [
                            'id' => 'video-one',
                            'title' => 'Video One',
                            'duration' => 125.4,
                            'thumbnails' => [
                                ['url' => 'https://example.test/thumb-small.jpg'],
                                ['url' => 'https://example.test/thumb-large.jpg'],
                            ],
                        ],
                        ['id' => '', 'title' => 'Missing id, should be skipped'],
                        ['id' => 'video-two'],
                    ],
                ],
            );
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new \RuntimeException('not called');
        }
    };

    $strategy = ytdlpDiscoveryStrategy($gateway);

    $items = $strategy->discover(new ResolvedInput(
        providerKey: 'youtube',
        inputType: 'channel',
        sourceUri: StashdUri::parse('https://www.youtube.com/channel/UCStashdDemoCh0012345678'),
        providerInputId: 'UCStashdDemoCh0012345678',
    ));

    expect($capturedUrl)->toBe('https://www.youtube.com/channel/UCStashdDemoCh0012345678/videos')
        ->and($capturedOptions?->toArray())->toContain('--flat-playlist')
        ->and($items)->toHaveCount(2)
        ->and($items[0]->providerItemId)->toBe('video-one')
        ->and($items[0]->title)->toBe('Video One')
        ->and($items[0]->durationSeconds)->toBe(125)
        ->and($items[0]->canonicalUri->toString())->toBe('https://www.youtube.com/watch?v=video-one')
        ->and($items[0]->thumbnailUri?->toString())->toBe('https://example.test/thumb-large.jpg')
        ->and($items[1]->providerItemId)->toBe('video-two')
        ->and($items[1]->title)->toBe('YouTube Video video-two')
        ->and($items[1]->durationSeconds)->toBeNull()
        ->and($items[1]->thumbnailUri)->toBeNull();
});

test('ytdlp discovery uses the playlist page for playlist inputs', function (): void {
    $capturedUrl = null;

    $gateway = new class ($capturedUrl) implements YtdlpGateway {
        public function __construct(private mixed &$capturedUrl)
        {
        }

        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            $this->capturedUrl = $url;

            return new VideoInfo(id: 'stub-playlist', title: 'Stub Playlist', raw: ['entries' => []]);
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new \RuntimeException('not called');
        }
    };

    $strategy = ytdlpDiscoveryStrategy($gateway);

    $items = $strategy->discover(new ResolvedInput(
        providerKey: 'youtube',
        inputType: 'playlist',
        sourceUri: StashdUri::parse('https://www.youtube.com/playlist?list=PLdemo'),
        providerInputId: 'PLdemo',
    ));

    expect($capturedUrl)->toBe('https://www.youtube.com/playlist?list=PLdemo')
        ->and($items)->toBe([]);
});

test('ytdlp discovery wraps a gateway failure as a provider exception', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('yt-dlp exited with code 1: boom');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new \RuntimeException('not called');
        }
    };

    $strategy = ytdlpDiscoveryStrategy($gateway);

    expect(fn () => $strategy->discover(new ResolvedInput(
        providerKey: 'youtube',
        inputType: 'channel',
        sourceUri: StashdUri::parse('https://www.youtube.com/channel/UCStashdDemoCh0012345678'),
        providerInputId: 'UCStashdDemoCh0012345678',
    )))->toThrow(ProviderException::class);
});
