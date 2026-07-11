<?php

declare(strict_types=1);

namespace App\Broadcasts\Podcasts;

use App\Broadcasts\BroadcastId;
use App\Broadcasts\BroadcastItemRecord;
use App\Broadcasts\BroadcastItemRepository;
use App\Broadcasts\BroadcastRecord;
use App\Broadcasts\BroadcastRepository;
use App\Support\PrefixedUlid;
use App\System\Secret\SecretRecord;
use App\System\Secret\SecretRepository;
use App\System\Secret\SecretsService;
use App\System\Secret\SecretType;

final readonly class PodcastTokenService
{
    public function __construct(
        private SecretsService $secrets,
        private SecretRepository $secretRecords,
        private PodcastTokenDigest $digests,
        private BroadcastRepository $broadcasts,
        private BroadcastItemRepository $broadcastItems,
    ) {
    }

    public function supports(BroadcastRecord $broadcast): bool
    {
        return $broadcast->type === 'podcast';
    }

    public function ensureBroadcastToken(BroadcastRecord $broadcast): string
    {
        $existing = $this->broadcastToken($broadcast);

        if ($existing !== null) {
            return $existing;
        }

        return $this->storeBroadcastToken($broadcast, $this->generateToken());
    }

    /**
     * Resolve a raw feed token back to its podcast broadcast.
     *
     * The blind digest finds one encrypted candidate; the decrypted token is
     * still confirmed with `hash_equals`. Revoked tokens are excluded by the
     * repository query.
     */
    public function findPodcastBroadcastByFeedToken(#[\SensitiveParameter] string $token): ?BroadcastRecord
    {
        if ($token === '') {
            return null;
        }

        $secret = $this->secretRecords->findActiveBroadcastTokenByDigest($this->digests->for($token));

        if ($secret === null || ! $this->matches($secret, $token)) {
            return null;
        }

        return $this->broadcasts->findPodcastByTokenSecretId((string) $secret->id);
    }

    public function broadcastToken(BroadcastRecord $broadcast): ?string
    {
        $secret = $this->secretForId($broadcast->tokenSecretId);

        if ($secret === null || $secret->revokedAt !== null) {
            return null;
        }

        return $this->secrets->get($secret->key);
    }

    public function rotateBroadcastToken(BroadcastRecord $broadcast): PodcastTokenRotationResult
    {
        $oldSecret = $this->secretForId($broadcast->tokenSecretId);
        $token = $this->storeBroadcastToken($broadcast, $this->generateToken());

        if ($oldSecret !== null) {
            $this->secrets->revoke($oldSecret->key);
        }

        return new PodcastTokenRotationResult(
            rotated: true,
            tokenPreview: self::preview($token),
            revokedOldSecret: $oldSecret !== null,
        );
    }

    /**
     * Resolve a raw episode token back to one of the matched broadcast's
     * items.
     *
     * The blind digest finds one encrypted candidate, scoped to the already
     * resolved broadcast after `hash_equals` confirms the decrypted token.
     */
    public function findBroadcastItemByEpisodeToken(
        BroadcastRecord $broadcast,
        #[\SensitiveParameter] string $itemToken,
    ): ?BroadcastItemRecord {
        if ($itemToken === '') {
            return null;
        }

        $secret = $this->secretRecords->findActiveBroadcastTokenByDigest($this->digests->for($itemToken));

        if ($secret === null || ! $this->matches($secret, $itemToken)) {
            return null;
        }

        return $this->broadcastItems->findByBroadcastAndTokenSecretId(
            BroadcastId::fromPrimaryKey($broadcast->id),
            (string) $secret->id,
        );
    }

    /** Backfills pre-index podcast tokens after the forward schema migration. */
    public function backfillMissingTokenDigests(): void
    {
        foreach ($this->secretRecords->listActiveBroadcastTokensWithoutDigest() as $secret) {
            try {
                $token = $this->secrets->get($secret->key);
            } catch (\RuntimeException) {
                continue;
            }

            if ($token === null) {
                continue;
            }

            $record = $this->secretRecords->findByKey($secret->key);

            if ($record === null || $record->tokenDigest !== null) {
                continue;
            }

            $record->tokenDigest = $this->digests->for($token);
            $this->secretRecords->save($record);
        }
    }

    public function ensureItemToken(BroadcastItemRecord $item): string
    {
        $existing = $this->itemToken($item);

        if ($existing !== null) {
            return $existing;
        }

        return $this->storeItemToken($item, $this->generateToken());
    }

    public function itemToken(BroadcastItemRecord $item): ?string
    {
        $secret = $this->secretForId($item->tokenSecretId);

        if ($secret === null || $secret->revokedAt !== null) {
            return null;
        }

        return $this->secrets->get($secret->key);
    }

    public static function preview(string $token): string
    {
        return substr($token, 0, 4) . '...' . substr($token, -6);
    }

    private function storeBroadcastToken(BroadcastRecord $broadcast, string $token): string
    {
        $key = sprintf('podcast:broadcast:%s:feed:%s', (string) $broadcast->id, bin2hex(random_bytes(6)));
        $this->secrets->put($key, SecretType::BroadcastToken, $token, [
            'scope' => 'podcast_feed',
            'broadcast_id' => (string) $broadcast->id,
            'broadcast_type' => $broadcast->type,
        ]);

        $secret = $this->requireSecretByKey($key);
        $secret->tokenDigest = $this->digests->for($token);
        $this->secretRecords->save($secret);
        $broadcast->tokenSecretId = (string) $secret->id;
        $broadcast->tokenPreview = self::preview($token);
        $this->broadcasts->save($broadcast);

        return $token;
    }

    private function storeItemToken(BroadcastItemRecord $item, string $token): string
    {
        $key = sprintf('podcast:broadcast_item:%s:episode:%s', (string) $item->id, bin2hex(random_bytes(6)));
        $this->secrets->put($key, SecretType::BroadcastToken, $token, [
            'scope' => 'podcast_episode',
            'broadcast_id' => (string) $item->broadcastId,
            'broadcast_item_id' => (string) $item->id,
        ]);

        $secret = $this->requireSecretByKey($key);
        $secret->tokenDigest = $this->digests->for($token);
        $this->secretRecords->save($secret);
        $item->tokenSecretId = (string) $secret->id;
        $item->tokenPreview = self::preview($token);
        $this->broadcastItems->save($item);

        return $token;
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function secretForId(?string $secretId): ?SecretRecord
    {
        if ($secretId === null || ! PrefixedUlid::isValid($secretId)) {
            return null;
        }

        return $this->secretRecords->find(PrefixedUlid::parse($secretId));
    }

    private function requireSecretByKey(string $key): SecretRecord
    {
        return $this->secretRecords->findByKey($key)
            ?? throw new \RuntimeException('Podcast token secret could not be persisted.');
    }

    private function matches(SecretRecord $secret, #[\SensitiveParameter] string $token): bool
    {
        try {
            $candidate = $this->secrets->get($secret->key);
        } catch (\RuntimeException) {
            return false;
        }

        return $candidate !== null && hash_equals($candidate, $token);
    }
}
