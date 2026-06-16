<?php

declare(strict_types=1);

namespace App\Domain\Vault;

use App\Domain\Download\DownloadRequest;
use App\Domain\Download\DownloadResult;
use App\Domain\Provider\ProviderDates;
use Tempest\DateTime\DateTime;

/**
 * Builds deterministic Vault sidecar JSON (metadata.json, source.json).
 *
 * Secret-like tokens are redacted before persistence (mirrors SecretsService rules).
 */
final class VaultSidecarBuilder
{
    public function metadataJson(DownloadRequest $request, DateTime $capturedAt): string
    {
        $payload = [
            'schema_version' => 1,
            'media_item_id' => $request->mediaItemId->toString(),
            'provider_key' => $request->providerKey,
            'provider_item_id' => $request->providerItemId,
            'canonical_uri' => $request->canonicalUri->toString(),
            'title' => $request->title,
            'duration_seconds' => $request->durationSeconds,
            'published_at' => $this->normalizeTimestamp($request->publishedAt),
            'thumbnail_uri' => $request->thumbnailUri?->toString(),
            'captured_at' => $capturedAt->toRfc3339(useZ: true),
        ];

        return $this->encode($payload);
    }

    public function sourceJson(DownloadRequest $request, DownloadResult $result): string
    {
        $payload = [
            'schema_version' => 1,
            'source_uri' => $result->sourceUri->toString(),
            'provider_key' => $request->providerKey,
            'provider_item_id' => $request->providerItemId,
            'download_policy' => $request->downloadPolicy->value,
            'attempted_at' => $result->attemptedAt->toRfc3339(useZ: true),
            'downloader' => [
                'implementation' => $result->implementation,
                'implementation_version' => $result->implementationVersion,
            ],
            'result' => array_merge(
                [
                    'implementation' => $result->implementation,
                    'implementation_version' => $result->implementationVersion,
                    'file_count' => count($result->files),
                ],
                $result->provenance,
            ),
        ];

        return $this->encode($payload);
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        return json_encode($this->redactPayload($payload), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $redacted[$key] = $this->redactPayload($value);

                continue;
            }

            if (is_string($value) && $this->shouldRedactField((string) $key, $value)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function shouldRedactField(string $key, string $value): bool
    {
        $normalizedKey = strtolower($key);

        if (in_array($normalizedKey, ['token', 'access_token', 'api_key', 'authorization', 'secret', 'password'], true)) {
            return true;
        }

        if (str_starts_with($value, 'Bearer ')) {
            return true;
        }

        return preg_match('/\b(?:Bearer\s+)?[A-Za-z0-9_\-]{32,}\b/', $value) === 1
            && ! str_contains($value, '://');
    }

    private function normalizeTimestamp(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $parsed = ProviderDates::tryParse($value);

        return $parsed?->toRfc3339(useZ: true);
    }
}
