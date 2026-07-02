<?php

declare(strict_types=1);

namespace App\Downloads\Ytdlp;

use App\Config\YtdlpConfig;
use App\Downloads\DownloadException;
use App\Stashes\DownloadPolicy;
use Ytdlphp\Option\AudioFormat;
use Ytdlphp\Option\MergeOutputFormat;
use Ytdlphp\Options;

final readonly class YtdlpOptionsBuilder
{
    public const string OUTPUT_TEMPLATE = 'stashd-original.%(ext)s';

    public function __construct(
        private YtdlpConfig $config,
    ) {
    }

    public function forPolicy(DownloadPolicy $policy): Options
    {
        return match ($policy) {
            DownloadPolicy::Video, DownloadPolicy::ManualDownload => $this->videoOptions(),
            DownloadPolicy::AudioOnly => $this->audioOptions(),
            DownloadPolicy::MetadataOnly => throw DownloadException::withCode(
                'download_policy_metadata_only',
                'Metadata-only stashes do not download media.',
            ),
        };
    }

    public function profileName(DownloadPolicy $policy): string
    {
        return match ($policy) {
            DownloadPolicy::Video, DownloadPolicy::ManualDownload => 'video_1080p_merged',
            DownloadPolicy::AudioOnly => 'audio_mp3_128k',
            DownloadPolicy::MetadataOnly => 'metadata_only',
        };
    }

    public function extractionOptions(): Options
    {
        return $this->withCrossCutting(Options::create());
    }

    /**
     * Flat listing for channel/playlist discovery -- id/title/url per entry
     * without resolving each video individually, which for a large channel
     * would mean one full extraction per video instead of one process call.
     */
    public function playlistOptions(): Options
    {
        return $this->withCrossCutting(Options::create())
            ->option('--flat-playlist');
    }

    private function videoOptions(): Options
    {
        return $this->withRetries(
            $this->withCrossCutting(
                Options::create()
                    ->format($this->config->videoFormatSelector)
                    ->output(self::OUTPUT_TEMPLATE)
                    ->mergeOutputFormat(MergeOutputFormat::Mp4, MergeOutputFormat::Mkv, MergeOutputFormat::Webm)
                    ->noPlaylist()
                    ->noWarnings()
                    ->option('--restrict-filenames'),
            ),
        );
    }

    private function audioOptions(): Options
    {
        $format = AudioFormat::tryFrom($this->config->audioFormat) ?? AudioFormat::Mp3;

        return $this->withRetries(
            $this->withCrossCutting(
                Options::create()
                    ->extractAudio()
                    ->audioFormat($format)
                    ->audioQuality($this->config->audioQualityKbps)
                    ->output(self::OUTPUT_TEMPLATE)
                    ->noPlaylist()
                    ->noWarnings()
                    ->option('--restrict-filenames'),
            ),
        );
    }

    /**
     * YouTube intermittently returns HTTP 403 on the media data URL (not the
     * metadata) under throttling -- a transient failure that a fresh attempt
     * usually clears. Without retries a single 403 hard-fails the whole item;
     * these let yt-dlp recover in-process instead.
     */
    private function withRetries(Options $options): Options
    {
        return $options
            ->option('--retries', '10')
            ->option('--fragment-retries', '10')
            ->option('--extractor-retries', '3');
    }

    /**
     * Humanizing flags shared by every yt-dlp invocation (including plain
     * metadata extraction, which is where bot-check walls are often hit
     * first): an optional exported cookies jar and a pause between requests.
     */
    private function withCrossCutting(Options $options): Options
    {
        if ($this->config->cookiesFile !== null) {
            $options = $options->cookies($this->config->cookiesFile);
        }

        if ($this->config->sleepRequestsSeconds > 0) {
            $options = $options->sleepRequests($this->config->sleepRequestsSeconds);
        }

        return $options;
    }
}
