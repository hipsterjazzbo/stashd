<?php

declare(strict_types=1);

namespace App\Services\MediaServer;

use App\Domain\MediaServer\MediaServerConnectionRecord;
use App\Domain\Secret\SecretRecord;
use App\Services\Secret\SecretsService;
use Tempest\Database\PrimaryKey;

final readonly class MediaServerTokenResolver
{
    public function __construct(
        private SecretsService $secrets,
    ) {
    }

    public function resolve(MediaServerConnectionRecord $connection): ?string
    {
        if ($connection->tokenSecretId === null) {
            return null;
        }

        $secret = SecretRecord::findById(new PrimaryKey($connection->tokenSecretId));

        if ($secret === null) {
            return null;
        }

        return $this->secrets->get($secret->key);
    }
}
