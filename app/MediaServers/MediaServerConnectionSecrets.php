<?php

declare(strict_types=1);

namespace App\MediaServers;

use App\System\Secret\SecretRecord;
use App\System\Secret\SecretsService;
use Tempest\Database\PrimaryKey;

final readonly class MediaServerConnectionSecrets
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
