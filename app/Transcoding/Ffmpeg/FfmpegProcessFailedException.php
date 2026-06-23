<?php

declare(strict_types=1);

namespace App\Transcoding\Ffmpeg;

use RuntimeException;
use Tempest\Process\ProcessResult;

/** Thrown by {@see FfmpegGatewayImpl} when the ffmpeg process exits non-zero. */
final class FfmpegProcessFailedException extends RuntimeException
{
    public function __construct(
        public readonly ProcessResult $result,
        string $message,
    ) {
        parent::__construct($message);
    }
}
