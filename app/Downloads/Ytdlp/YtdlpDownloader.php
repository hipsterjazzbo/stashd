<?php

declare(strict_types=1);

namespace App\Downloads\Ytdlp;

use App\Config\YtdlpConfig;
use App\Downloads\DownloadedFile;
use App\Downloads\DownloaderInterface;
use App\Downloads\DownloadException;
use App\Downloads\DownloadProbeResult;
use App\Downloads\DownloadRequest;
use App\Downloads\DownloadResult;
use App\Stashes\DownloadPolicy;
use App\Vault\AssetKind;
use App\Vault\AssetRole;
use App\Vault\VaultSidecarBuilder;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;
use Tempest\Process\Exceptions\ProcessHasTimedOut;

use function Tempest\Support\str;

use Ytdlphp\Exception\ProcessFailedException;
use Ytdlphp\Exception\YtDlpException;
use Ytdlphp\Metadata\VideoInfo;

final readonly class YtdlpDownloader implements DownloaderInterface
{
    public const string IMPLEMENTATION = 'ytdlphp';

    public function __construct(
        private YtdlpConfig $config,
        private YtdlpGateway $gateway,
        private YtdlpOptionsBuilder $options,
        private VaultSidecarBuilder $sidecars,
    ) {
    }

    public function implementationName(): string
    {
        return self::IMPLEMENTATION;
    }

    public function implementationVersion(): ?string
    {
        return $this->gateway->probe()->version;
    }

    public function probe(): DownloadProbeResult
    {
        $probe = $this->gateway->probe();

        return new DownloadProbeResult(
            available: $this->config->realDownloadsEnabled() && $probe->available,
            implementation: self::IMPLEMENTATION,
            implementationVersion: $probe->version,
            message: $probe->message,
        );
    }

    public function download(DownloadRequest $request, ?callable $onProgress = null): DownloadResult
    {
        if (! $this->config->realDownloadsEnabled()) {
            throw DownloadException::withCode(
                'download_ytdlp_unavailable',
                'Real downloads are disabled. Set STASHD_REAL_DOWNLOADS_ENABLED=1 to enable ytdlphp.',
            );
        }

        if (! is_dir($request->tempDirectory) || ! is_writable($request->tempDirectory)) {
            throw DownloadException::withCode('temp_not_writable', 'Temp download directory is not writable.');
        }

        $gatewayProbe = $this->gateway->probe();

        if (! $gatewayProbe->available) {
            throw DownloadException::withCode(
                'download_ytdlp_unavailable',
                $gatewayProbe->message ?? 'yt-dlp binary is not available.',
            );
        }

        $attemptedAt = DateTime::now(Timezone::UTC);
        $sourceUrl = $request->canonicalUri->toString();

        try {
            $videoInfo = $this->gateway->extractInfo($sourceUrl, $request->tempDirectory, $this->options->extractionOptions());
            $downloadOptions = $this->options->forPolicy($request->downloadPolicy);
            $this->gateway->download($sourceUrl, $request->tempDirectory, $downloadOptions, $onProgress);
            $original = $this->resolveOriginalOutput($request->tempDirectory);
            $vaultFilename = $this->vaultFilename($original, $request->downloadPolicy);
            $renamedPath = rtrim($request->tempDirectory, '/') . '/' . $vaultFilename;

            if ($original !== $renamedPath && ! rename($original, $renamedPath)) {
                throw DownloadException::withCode(
                    'download_ytdlp_unexpected_output',
                    'Unable to normalize downloaded file in temp staging.',
                );
            }

            $files = [$this->originalFile($renamedPath, $vaultFilename, $request->downloadPolicy, $videoInfo)];
            $files[] = $this->writeMetadata($request, $attemptedAt, $videoInfo);
            $files[] = $this->writeSource(
                $request,
                $attemptedAt,
                $videoInfo,
                $gatewayProbe,
                $downloadOptions,
                $renamedPath,
            );

            return new DownloadResult(
                files: $files,
                implementation: self::IMPLEMENTATION,
                implementationVersion: $gatewayProbe->version,
                sourceUri: $request->canonicalUri,
                attemptedAt: $attemptedAt,
                provenance: [
                    'ytdlp_binary' => $gatewayProbe->binary,
                    'ytdlp_version' => $gatewayProbe->version,
                    'format_profile' => $this->options->profileName($request->downloadPolicy),
                    'temp_output_path' => $vaultFilename,
                    'extract_info_id' => $videoInfo->id,
                ],
            );
        } catch (DownloadException $exception) {
            throw $exception;
        } catch (ProcessHasTimedOut $exception) {
            throw DownloadException::withCode(
                'download_ytdlp_timeout',
                'yt-dlp download timed out.',
                $exception,
            );
        } catch (ProcessFailedException $exception) {
            $retryableCode = $this->classifyRetryableFailure($exception->getMessage());

            throw DownloadException::withCode(
                $retryableCode ?? 'download_ytdlp_failed',
                $this->redact($exception->getMessage()),
                $exception,
                retryable: $retryableCode !== null,
            );
        } catch (YtDlpException $exception) {
            throw $this->mapYtDlpException($exception);
        } catch (\Throwable $throwable) {
            throw DownloadException::withCode(
                'download_ytdlp_failed',
                $this->redact($throwable->getMessage()),
                $throwable,
            );
        }
    }

    private function mapYtDlpException(YtDlpException $exception): DownloadException
    {
        $message = $this->redact($exception->getMessage());

        if (str($message)->contains(['Invalid URL', 'invalid url', 'Unsupported URL'])) {
            return DownloadException::withCode('download_ytdlp_invalid_uri', $message, $exception);
        }

        $retryableCode = $this->classifyRetryableFailure($exception->getMessage());

        if ($retryableCode !== null) {
            return DownloadException::withCode($retryableCode, $message, $exception, retryable: true);
        }

        return DownloadException::withCode('download_ytdlp_failed', $message, $exception);
    }

    /**
     * Detects YouTube's transient anti-automation responses so the caller can
     * back off and retry instead of permanently failing the item. Matched
     * against the raw (pre-redaction, pre-truncation) message so a long
     * stderr can't push the pattern past redact()'s 500-char cutoff.
     */
    private function classifyRetryableFailure(string $rawMessage): ?string
    {
        $needle = str($rawMessage)->lower();

        if ($needle->contains(['sign in to confirm', 'not a bot'])) {
            return 'download_ytdlp_bot_check';
        }

        if ($needle->contains(['http error 429', 'too many requests'])) {
            return 'download_ytdlp_rate_limited';
        }

        return null;
    }

    private function resolveOriginalOutput(string $tempDirectory): string
    {
        $root = realpath($tempDirectory);

        if ($root === false) {
            throw DownloadException::withCode(
                'download_ytdlp_no_output',
                'Temp staging directory is unavailable after download.',
            );
        }

        $matches = glob($root . '/stashd-original.*') ?: [];

        foreach ($matches as $path) {
            if (! is_file($path)) {
                continue;
            }

            $resolved = realpath($path);

            if ($resolved !== false && str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
                return $resolved;
            }
        }

        throw DownloadException::withCode(
            'download_ytdlp_no_output',
            'yt-dlp did not produce the expected stashd-original output file.',
        );
    }

    private function vaultFilename(string $tempPath, DownloadPolicy $policy): string
    {
        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);

        if ($extension === '') {
            throw DownloadException::withCode(
                'download_ytdlp_unexpected_output',
                'Downloaded file is missing a file extension.',
            );
        }

        return match ($policy) {
            DownloadPolicy::AudioOnly => 'original.' . $extension,
            default => 'original.' . $extension,
        };
    }

    private function originalFile(
        string $path,
        string $filename,
        DownloadPolicy $policy,
        VideoInfo $videoInfo,
    ): DownloadedFile {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';

        return new DownloadedFile(
            tempPath: $path,
            filename: $filename,
            role: AssetRole::VaultOriginal,
            kind: $policy === DownloadPolicy::AudioOnly ? AssetKind::Audio : AssetKind::Video,
            mimeType: $this->mimeForExtension($extension),
            container: $extension,
            sizeBytes: is_file($path) ? filesize($path) : null,
            durationSeconds: $videoInfo->duration !== null ? (int) round($videoInfo->duration) : null,
        );
    }

    private function writeMetadata(DownloadRequest $request, DateTime $capturedAt, VideoInfo $videoInfo): DownloadedFile
    {
        $enriched = new DownloadRequest(
            mediaItemId: $request->mediaItemId,
            stashId: $request->stashId,
            providerKey: $request->providerKey,
            providerItemId: $request->providerItemId,
            canonicalUri: $request->canonicalUri,
            downloadPolicy: $request->downloadPolicy,
            tempDirectory: $request->tempDirectory,
            force: $request->force,
            durationSeconds: $request->durationSeconds ?? ($videoInfo->duration !== null ? (int) round($videoInfo->duration) : null),
            thumbnailUri: $request->thumbnailUri,
            title: $request->title ?? ($videoInfo->title !== '' ? $videoInfo->title : null),
            publishedAt: $request->publishedAt,
        );

        $path = $this->tempPath($request, 'metadata.json');
        $payload = $this->sidecars->metadataJson($enriched, $capturedAt);
        file_put_contents($path, $payload);

        return new DownloadedFile(
            tempPath: $path,
            filename: 'metadata.json',
            role: AssetRole::MetadataJson,
            kind: AssetKind::Metadata,
            mimeType: 'application/json',
            container: 'json',
            sizeBytes: strlen($payload),
        );
    }

    private function writeSource(
        DownloadRequest $request,
        DateTime $attemptedAt,
        VideoInfo $videoInfo,
        YtdlpProbeResult $probe,
        \Ytdlphp\Options $downloadOptions,
        string $tempOutputPath,
    ): DownloadedFile {
        $path = $this->tempPath($request, 'source.json');
        $result = new DownloadResult(
            files: [],
            implementation: self::IMPLEMENTATION,
            implementationVersion: $probe->version,
            sourceUri: $request->canonicalUri,
            attemptedAt: $attemptedAt,
            provenance: [
                'ytdlp_binary' => $probe->binary,
                'ytdlp_version' => $probe->version,
                'format_profile' => $this->options->profileName($request->downloadPolicy),
                'temp_output_path' => basename($tempOutputPath),
                'options' => $this->redactOptions($downloadOptions->toArray()),
                'extract_info' => $this->redactExtractInfo($videoInfo),
            ],
        );
        $payload = $this->sidecars->sourceJson($request, $result);
        file_put_contents($path, $payload);

        return new DownloadedFile(
            tempPath: $path,
            filename: 'source.json',
            role: AssetRole::SourceJson,
            kind: AssetKind::Metadata,
            mimeType: 'application/json',
            container: 'json',
            sizeBytes: strlen($payload),
        );
    }

    /**
     * yt-dlp CLI arguments are a flat list (`['--cookies', '/path', ...]`),
     * not a keyed array -- the cookies jar path is the sensitive value here
     * (it grants an authenticated YouTube session), so it's blanked before
     * this list is embedded in source.json provenance.
     *
     * @param list<string> $arguments
     * @return list<string>
     */
    private function redactOptions(array $arguments): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === '--cookies' && isset($arguments[$index + 1])) {
                $arguments[$index + 1] = '[REDACTED]';
            }
        }

        return $arguments;
    }

    /** @return array<string, mixed> */
    private function redactExtractInfo(VideoInfo $videoInfo): array
    {
        $raw = $videoInfo->raw;

        unset($raw['cookies'], $raw['http_headers']);

        return $raw;
    }

    private function tempPath(DownloadRequest $request, string $filename): string
    {
        return rtrim($request->tempDirectory, '/') . '/' . $filename;
    }

    private function mimeForExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'opus' => 'audio/opus',
            default => 'application/octet-stream',
        };
    }

    private function redact(string $message): string
    {
        if (strlen($message) > 500) {
            $message = substr($message, 0, 500) . '…';
        }

        $redacted = preg_replace(
            '/\b(?:Bearer\s+)?[A-Za-z0-9_\-]{32,}\b/',
            '[REDACTED]',
            $message,
        );

        return $redacted ?? $message;
    }
}
