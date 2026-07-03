<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;
use SensitiveParameter;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;
use Tempest\Http\Request;

final readonly class AuthService
{
    /**
     * Reserved token + cookie names for the browser session. The web UI never
     * sees a raw API token: login mints this single rotating token and ships it
     * in an HttpOnly cookie. It is hidden from the user-facing token list.
     */
    public const string WEB_SESSION_TOKEN_NAME = '__web_session__';

    public const string SESSION_COOKIE = 'stashd_session';

    public const int WEB_SESSION_TTL_SECONDS = 30 * 24 * 60 * 60;

    public function __construct(
        private UserRepository $users,
        private ApiTokenRepository $tokens,
        private AuthContext $context,
    ) {
    }

    public function isSetupRequired(): bool
    {
        return $this->users->count() === 0;
    }

    public function setupAdmin(string $email, #[SensitiveParameter] string $password): UserRecord
    {
        if (! $this->isSetupRequired()) {
            throw new SetupAlreadyCompleted('Admin account already exists.');
        }

        $user = $this->users->createAdmin(
            email: $email,
            passwordHash: $this->hashPassword($password),
        );

        $this->context->set($user);

        return $user;
    }

    public function login(string $email, #[SensitiveParameter] string $password): UserRecord
    {
        if ($this->isSetupRequired()) {
            throw new SetupRequired('Complete admin setup before logging in.');
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || ! password_verify($password, $user->passwordHash)) {
            throw new InvalidCredentials('Invalid email or password.');
        }

        $this->context->set($user);

        return $user;
    }

    public function logout(): void
    {
        $this->context->set(null);
    }

    public function resolveFromRequest(Request $request): ?UserRecord
    {
        $authorization = $this->headerValue($request, 'Authorization');

        // Bearer header keeps precedence: an explicit (even if invalid) token
        // must not silently fall back to a cookie or session.
        if ($authorization !== null && preg_match('/^Bearer\s+(\S+)\s*$/i', $authorization, $matches)) {
            return $this->resolveFromToken($matches[1]);
        }

        $cookie = $request->cookies[self::SESSION_COOKIE] ?? null;

        if ($cookie !== null && is_string($cookie->value) && $cookie->value !== '') {
            return $this->resolveFromToken($cookie->value);
        }

        // No fallback to Tempest's native Authenticator/Session here: those
        // are singletons that live for the lifetime of a RoadRunner worker,
        // so one user's native session would leak into every other request
        // that worker happens to serve afterward. Bearer header and the
        // stashd_session cookie are the only supported auth paths.
        return null;
    }

    private function resolveFromToken(#[SensitiveParameter] string $plainToken): ?UserRecord
    {
        $token = $this->tokens->findByHash(hash('sha256', $plainToken));

        if ($token === null) {
            return null;
        }

        if ($token->expiresAt !== null && $token->expiresAt->isBefore(DateTime::now(Timezone::UTC))) {
            return null;
        }

        $user = $this->users->findById($token->userId);

        if ($user === null) {
            return null;
        }

        $token->lastUsedAt = DateTime::now(Timezone::UTC);
        $token->save();
        $this->context->set($user);

        return $user;
    }

    /**
     * Mints the single rotating web-session token, replacing any prior one, and
     * returns the raw value for the caller to place in the session cookie.
     */
    public function issueWebSessionToken(UserRecord $user): string
    {
        $this->revokeWebSessionTokens($user);

        $expiresAt = DateTime::now(Timezone::UTC)->plusSeconds(self::WEB_SESSION_TTL_SECONDS);

        return $this->createApiToken($user, self::WEB_SESSION_TOKEN_NAME, expiresAt: $expiresAt)['token'];
    }

    public function revokeWebSessionTokens(UserRecord $user): void
    {
        foreach ($this->tokens->listForUser(UserId::parse((string) $user->id)) as $token) {
            if ($token->name === self::WEB_SESSION_TOKEN_NAME) {
                $this->tokens->revoke(ApiTokenId::parse((string) $token->id));
            }
        }
    }

    /** @return array{id: string, token: string, token_preview: string, name: string} */
    public function createApiToken(UserRecord $user, string $name, ?array $scopes = null, ?DateTime $expiresAt = null): array
    {
        $plainToken = 'stashd_pat_' . bin2hex(random_bytes(24));
        $record = $this->tokens->create(
            userId: UserId::parse((string) $user->id),
            name: $name,
            tokenHash: hash('sha256', $plainToken),
            tokenPreview: substr($plainToken, 0, 20) . '…',
            scopes: $scopes,
            expiresAt: $expiresAt,
        );

        return [
            'id' => (string) $record->id,
            'token' => $plainToken,
            'token_preview' => (string) $record->tokenPreview,
            'name' => $record->name,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listApiTokens(UserRecord $user): array
    {
        $tokens = array_filter(
            $this->tokens->listForUser(UserId::parse((string) $user->id)),
            static fn ($token): bool => $token->name !== self::WEB_SESSION_TOKEN_NAME,
        );

        return array_values(array_map(
            static fn ($token): array => [
                'id' => (string) $token->id,
                'name' => $token->name,
                'token_preview' => $token->tokenPreview,
                'scopes' => $token->scopes?->toArray() ?? [],
                'last_used_at' => $token->lastUsedAt?->toRfc3339(useZ: true),
                'expires_at' => $token->expiresAt?->toRfc3339(useZ: true),
                'created_at' => $token->createdAt?->toRfc3339(useZ: true),
            ],
            $tokens,
        ));
    }

    public function revokeApiToken(UserRecord $user, ApiTokenId $tokenId): void
    {
        $record = ApiTokenRecord::findById(new \Tempest\Database\PrimaryKey($tokenId->toString()));

        if ($record === null || (string) $record->userId !== (string) $user->id) {
            return;
        }

        $this->tokens->revoke($tokenId);
    }

    private function hashPassword(#[SensitiveParameter] string $password): string
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    private function headerValue(Request $request, string $name): ?string
    {
        return $request->headers->get($name);
    }
}
