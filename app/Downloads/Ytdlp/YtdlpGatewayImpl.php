<?php

declare(strict_types=1);

namespace App\Downloads\Ytdlp;

use App\Config\YtdlpConfig;
use Ytdlphp\Metadata\VideoInfo;
use Ytdlphp\Options;
use Ytdlphp\YtDlp;

final readonly class YtdlpGatewayImpl implements YtdlpGateway
{
    public function __construct(
        private YtdlpConfig $config,
    ) {
    }

    public function probe(): YtdlpProbeResult
    {
        try {
            $client = $this->client(sys_get_temp_dir());
            $version = $client->getVersion();

            return new YtdlpProbeResult(
                available: true,
                binary: $this->config->binary,
                version: $version,
            );
        } catch (\Throwable $throwable) {
            return new YtdlpProbeResult(
                available: false,
                binary: $this->config->binary,
                message: $this->redact($throwable->getMessage()),
            );
        }
    }

    public function extractInfo(string $url, string $workingDirectory): VideoInfo
    {
        return $this->client($workingDirectory)->extractInfo($url);
    }

    public function download(string $url, string $workingDirectory, Options $options, ?callable $onProgress = null): \Ytdlphp\DownloadResult
    {
        return $this->client($workingDirectory)->download($url, $options, $onProgress);
    }

    private function client(string $workingDirectory): YtDlp
    {
        return new YtDlp(
            binary: $this->config->binary,
            workingDirectory: $workingDirectory,
            timeout: $this->config->timeoutSeconds,
        );
    }

    private function redact(string $message): string
    {
        $redacted = preg_replace(
            '/\b(?:Bearer\s+)?[A-Za-z0-9_\-]{32,}\b/',
            '[REDACTED]',
            $message,
        );

        return $redacted ?? $message;
    }
}
