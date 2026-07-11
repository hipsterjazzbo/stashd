<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Download;

use App\Config\YtdlpConfig;
use App\Downloads\DownloadException;
use App\Downloads\DownloadRequest;
use App\Downloads\Ytdlp\StubYtdlpGateway;
use App\Downloads\Ytdlp\YtdlpDownloader;
use App\Downloads\Ytdlp\YtdlpGateway;
use App\Downloads\Ytdlp\YtdlpOptionsBuilder;
use App\Downloads\Ytdlp\YtdlpProbeResult;
use App\Providers\StashdUri;
use App\Stashes\DownloadPolicy;
use App\Stashes\StashId;
use App\Vault\MediaItemId;
use App\Vault\VaultSidecarBuilder;
use Tempest\Process\Exceptions\ProcessHasTimedOut;
use Tempest\Process\ProcessResult;
use Ytdlphp\Exception\ProcessFailedException;
use Ytdlphp\Metadata\VideoInfo;
use Ytdlphp\Options;

function ytdlpTestConfig(bool $enabled = true, ?string $cookiesFile = null): YtdlpConfig
{
    return new YtdlpConfig(
        binary: 'yt-dlp',
        timeoutSeconds: 120,
        realDownloadsEnabledDefault: $enabled,
        videoFormatSelector: 'bestvideo[height<=1080]+bestaudio/best',
        audioFormat: 'mp3',
        audioQualityKbps: 128,
        cookiesFile: $cookiesFile,
    );
}

function ytdlpDownloadRequest(DownloadPolicy $policy, string $temp): DownloadRequest
{
    return new DownloadRequest(
        mediaItemId: MediaItemId::parse('media_01J00000000000000000000001'),
        stashId: StashId::parse('stash_01J00000000000000000000001'),
        providerKey: 'youtube',
        providerItemId: 'demoVideo01',
        canonicalUri: StashdUri::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        downloadPolicy: $policy,
        tempDirectory: $temp,
        title: 'Demo Video',
    );
}

function ytdlpDownloader(YtdlpGateway $gateway, bool $enabled = true): YtdlpDownloader
{
    return new YtdlpDownloader(
        config: ytdlpTestConfig($enabled),
        gateway: $gateway,
        options: new YtdlpOptionsBuilder(ytdlpTestConfig($enabled)),
        sidecars: new VaultSidecarBuilder(),
    );
}

test('ytdlp options builder configures video profile', function (): void {
    $builder = new YtdlpOptionsBuilder(ytdlpTestConfig());
    $options = $builder->forPolicy(DownloadPolicy::Video);

    expect($options->toArray())->toContain('-f')
        ->and($options->toArray())->toContain('bestvideo[height<=1080]+bestaudio/best')
        ->and($options->toArray())->toContain('stashd-original.%(ext)s')
        ->and($builder->profileName(DownloadPolicy::Video))->toBe('video_1080p_merged');
});

test('ytdlp options builder configures audio profile', function (): void {
    $builder = new YtdlpOptionsBuilder(ytdlpTestConfig());
    $options = $builder->forPolicy(DownloadPolicy::AudioOnly);

    expect($options->toArray())->toContain('-x')
        ->and($options->toArray())->toContain('--audio-format')
        ->and($options->toArray())->toContain('mp3')
        ->and($options->toArray())->toContain('--audio-quality')
        ->and($options->toArray())->toContain('128');
});

test('ytdlp downloader uses temp staging directory via stub gateway', function (): void {
    $temp = sys_get_temp_dir() . '/stashd-ytdlp-' . bin2hex(random_bytes(4));
    mkdir($temp, 0775, true);
    $gateway = new StubYtdlpGateway();
    $downloader = ytdlpDownloader($gateway);

    $result = $downloader->download(ytdlpDownloadRequest(DownloadPolicy::Video, $temp));

    expect($gateway->downloadCalls)->toBe(1)
        ->and($gateway->extractInfoCalls)->toBe(1)
        ->and($result->implementation)->toBe('ytdlphp')
        ->and(file_exists($temp . '/original.mp4'))->toBeTrue()
        ->and(file_exists($temp . '/metadata.json'))->toBeTrue()
        ->and(file_exists($temp . '/source.json'))->toBeTrue();

    array_map('unlink', glob($temp . '/*') ?: []);
    rmdir($temp);
});

test('ytdlp downloader forwards a progress callback through to the gateway', function (): void {
    $temp = sys_get_temp_dir() . '/stashd-ytdlp-' . bin2hex(random_bytes(4));
    mkdir($temp, 0775, true);
    $gateway = new StubYtdlpGateway();
    $downloader = ytdlpDownloader($gateway);

    $updates = [];
    $downloader->download(
        ytdlpDownloadRequest(DownloadPolicy::Video, $temp),
        onProgress: function (\Ytdlphp\DownloadProgress $progress) use (&$updates): void {
            $updates[] = $progress;
        },
    );

    expect($updates)->not->toBeEmpty()
        ->and($updates[array_key_last($updates)]->percent)->toBe(100.0);

    array_map('unlink', glob($temp . '/*') ?: []);
    rmdir($temp);
});

test('ytdlp downloader rejects disabled real downloads', function (): void {
    $downloader = ytdlpDownloader(new StubYtdlpGateway(), enabled: false);

    try {
        $downloader->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_unavailable');

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp downloader rejects unavailable binary', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(false, 'missing-yt-dlp', message: 'Binary missing.');
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
    };

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_unavailable');

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp downloader maps timeout failures', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            return new VideoInfo(id: 'x', title: 'x');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            $process = new \Symfony\Component\Process\Process(['yt-dlp']);
            $symfonyTimeout = new \Symfony\Component\Process\Exception\ProcessTimedOutException(
                $process,
                \Symfony\Component\Process\Exception\ProcessTimedOutException::TYPE_GENERAL,
            );

            throw new ProcessHasTimedOut(
                new ProcessResult(124, '', 'timed out'),
                $symfonyTimeout,
            );
        }
    };

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_timeout');

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp downloader maps process failures', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            return new VideoInfo(id: 'x', title: 'x');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new ProcessFailedException(
                new ProcessResult(1, '', 'download failed'),
                new \Tempest\Process\PendingProcess(['yt-dlp']),
            );
        }
    };

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_failed')
            ->and($exception->retryable)->toBeFalse();

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp downloader classifies bot-check failures as retryable', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new ProcessFailedException(
                new ProcessResult(1, '', "ERROR: [youtube] x: Sign in to confirm you're not a bot"),
                new \Tempest\Process\PendingProcess(['yt-dlp']),
            );
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new \RuntimeException('not called');
        }
    };

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_bot_check')
            ->and($exception->retryable)->toBeTrue();

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp downloader classifies rate-limit failures as retryable', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            return new VideoInfo(id: 'x', title: 'x');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new ProcessFailedException(
                new ProcessResult(1, '', 'ERROR: HTTP Error 429: Too Many Requests'),
                new \Tempest\Process\PendingProcess(['yt-dlp']),
            );
        }
    };

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_rate_limited')
            ->and($exception->retryable)->toBeTrue();

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp downloader classifies bare "video is not available" failures as retryable', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new ProcessFailedException(
                new ProcessResult(1, '', 'ERROR: [youtube] x: Video unavailable. This video is not available'),
                new \Tempest\Process\PendingProcess(['yt-dlp']),
            );
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new \RuntimeException('not called');
        }
    };

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_transient_unavailable')
            ->and($exception->retryable)->toBeTrue();

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp downloader keeps a specific removal reason non-retryable', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new ProcessFailedException(
                new ProcessResult(1, '', 'ERROR: [youtube] x: Video unavailable. This video is not available because the YouTube account associated with this video has been terminated'),
                new \Tempest\Process\PendingProcess(['yt-dlp']),
            );
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            throw new \RuntimeException('not called');
        }
    };

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, sys_get_temp_dir()));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_failed')
            ->and($exception->retryable)->toBeFalse();

        return;
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});

test('ytdlp options builder applies configured cookies file to extraction, video, and audio options', function (): void {
    $builder = new YtdlpOptionsBuilder(ytdlpTestConfig(cookiesFile: '/secrets/cookies.txt'));

    expect($builder->extractionOptions()->toArray())->toContain('--cookies')->toContain('/secrets/cookies.txt')
        ->and($builder->forPolicy(DownloadPolicy::Video)->toArray())->toContain('--cookies')
        ->and($builder->forPolicy(DownloadPolicy::AudioOnly)->toArray())->toContain('--cookies');
});

test('ytdlp downloader redacts the cookies path from source.json provenance', function (): void {
    $temp = sys_get_temp_dir() . '/stashd-ytdlp-' . bin2hex(random_bytes(4));
    mkdir($temp, 0775, true);
    $gateway = new StubYtdlpGateway();
    $config = ytdlpTestConfig(cookiesFile: '/secrets/cookies.txt');
    $downloader = new YtdlpDownloader(
        config: $config,
        gateway: $gateway,
        options: new YtdlpOptionsBuilder($config),
        sidecars: new VaultSidecarBuilder(),
    );

    $downloader->download(ytdlpDownloadRequest(DownloadPolicy::Video, $temp));

    $source = json_decode(file_get_contents($temp . '/source.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($source['result']['options'])->toContain('[REDACTED]')
        ->and($source['result']['options'])->not->toContain('/secrets/cookies.txt');

    array_map('unlink', glob($temp . '/*') ?: []);
    rmdir($temp);
});

test('ytdlp downloader rejects missing output files', function (): void {
    $gateway = new class () implements YtdlpGateway {
        public function probe(): YtdlpProbeResult
        {
            return new YtdlpProbeResult(true, 'yt-dlp', '2026.01.01');
        }

        public function extractInfo(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            return new VideoInfo(id: 'x', title: 'x');
        }

        public function extractPlaylist(string $url, string $workingDirectory, ?Options $options = null): VideoInfo
        {
            throw new \RuntimeException('not called');
        }

        public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
        {
            return new \Ytdlphp\DownloadResult(new ProcessResult(0, 'ok', ''));
        }
    };

    $temp = sys_get_temp_dir() . '/stashd-ytdlp-empty-' . bin2hex(random_bytes(4));
    mkdir($temp, 0775, true);

    try {
        ytdlpDownloader($gateway)->download(ytdlpDownloadRequest(DownloadPolicy::Video, $temp));
    } catch (DownloadException $exception) {
        expect($exception->errorCode)->toBe('download_ytdlp_no_output');

        return;
    } finally {
        rmdir($temp);
    }

    throw new \RuntimeException('Expected DownloadException was not thrown.');
});
