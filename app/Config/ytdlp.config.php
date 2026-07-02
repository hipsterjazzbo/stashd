<?php

declare(strict_types=1);

use App\Config\YtdlpConfig;

use function Tempest\env;

$environment = env('ENVIRONMENT', 'local');
$realDownloadsDefault = $environment === 'testing' ? '0' : '1';

$minDelayRaw = env('STASHD_YTDLP_MIN_DELAY_SECONDS', $environment === 'testing' ? '0' : '3');
$maxDelayRaw = env('STASHD_YTDLP_MAX_DELAY_SECONDS', $environment === 'testing' ? '0' : '8');
$cookiesFileRaw = env('STASHD_YTDLP_COOKIES_FILE');
$sleepRequestsRaw = env('STASHD_YTDLP_SLEEP_REQUESTS_SECONDS', $environment === 'testing' ? '0' : '1');

return new YtdlpConfig(
    binary: env('STASHD_YTDLP_BINARY', 'yt-dlp'),
    timeoutSeconds: (int) env('STASHD_YTDLP_TIMEOUT', '600'),
    realDownloadsEnabledDefault: filter_var(
        env('STASHD_REAL_DOWNLOADS_ENABLED', $realDownloadsDefault),
        FILTER_VALIDATE_BOOL,
    ),
    videoFormatSelector: env(
        'STASHD_YTDLP_VIDEO_FORMAT',
        'bestvideo[height<=1080]+bestaudio/best[height<=1080]/best',
    ),
    audioFormat: env('STASHD_YTDLP_AUDIO_FORMAT', 'mp3'),
    audioQualityKbps: (int) env('STASHD_YTDLP_AUDIO_QUALITY_KBPS', '128'),
    minDelaySeconds: is_numeric($minDelayRaw) ? (int) $minDelayRaw : 0,
    maxDelaySeconds: is_numeric($maxDelayRaw) ? (int) $maxDelayRaw : 0,
    cookiesFile: is_string($cookiesFileRaw) ? $cookiesFileRaw : null,
    sleepRequestsSeconds: is_numeric($sleepRequestsRaw) ? (float) $sleepRequestsRaw : 0.0,
);
