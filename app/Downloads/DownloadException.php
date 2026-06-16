<?php

declare(strict_types=1);

namespace App\Downloads;

use RuntimeException;

final class DownloadException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function withCode(string $errorCode, string $message, ?\Throwable $previous = null): self
    {
        return new self($message, $errorCode, $previous);
    }
}
