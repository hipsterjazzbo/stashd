<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Domain\Auth\UserRecord;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\ApiTokenRepository;
use App\Infrastructure\Persistence\RecordTimestamps;
use App\Infrastructure\Persistence\UserRepository;
use RuntimeException;
use SensitiveParameter;
use Tempest\Auth\Authentication\Authenticator;
use Tempest\Http\Request;

final readonly class AuthService
{
    public function __construct(
        private UserRepository $users,
        private ApiTokenRepository $tokens,
        private Authenticator $authenticator,
        private AuthContext $context,
    ) {
    }

    public function isSetupRequired(): bool
    {
        return $this->users->count() === 0;
    }

    public function setupOwner(string $email, string $username, #[SensitiveParameter] string $password): UserRecord
    {
        if (! $this->isSetupRequired()) {
            throw new SetupAlreadyCompleted('Owner account already exists.');
        }

        $user = $this->users->createOwner(
            email: $email,
            username: $username,
            passwordHash: $this->hashPassword($password),
        );

        $this->authenticator->authenticate($user);
        $this->context->set($user);

        return $user;
    }

    public function login(string $email, #[SensitiveParameter] string $password): UserRecord
    {
        if ($this->isSetupRequired()) {
            throw new SetupRequired('Complete owner setup before logging in.');
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || ! password_verify($password, $user->passwordHash)) {
            throw new InvalidCredentials('Invalid email or password.');
        }

        $this->authenticator->authenticate($user);
        $this->context->set($user);

        return $user;
    }

    public function logout(): void
    {
        $this->authenticator->deauthenticate();
        $this->context->set(null);
    }

    public function currentUser(): ?UserRecord
    {
        $contextUser = $this->context->user();

        if ($contextUser !== null) {
            return $contextUser;
        }

        $authenticatable = $this->authenticator->current();

        if ($authenticatable instanceof UserRecord) {
            $this->context->set($authenticatable);

            return $authenticatable;
        }

        return null;
    }

    public function resolveFromRequest(Request $request): ?UserRecord
    {
        $authorization = $this->headerValue($request, 'Authorization');

        if ($authorization !== null && preg_match('/^Bearer\s+(\S+)\s*$/i', $authorization, $matches)) {
            $tokenHash = hash('sha256', $matches[1]);
            $token = $this->tokens->findByHash($tokenHash);

            if ($token === null) {
                return null;
            }

            if ($token->expiresAt !== null && strtotime($token->expiresAt) < time()) {
                return null;
            }

            $user = $this->users->findById($token->userId);

            if ($user === null) {
                return null;
            }

            $token->lastUsedAt = RecordTimestamps::now();
            $token->save();
            $this->context->set($user);

            return $user;
        }

        $sessionUser = $this->currentUser();

        if ($sessionUser !== null) {
            return $sessionUser;
        }

        return null;
    }

    /** @return array{id: string, token: string, token_preview: string, name: string} */
    public function createApiToken(UserRecord $user, string $name, ?array $scopes = null, ?string $expiresAt = null): array
    {
        $plainToken = 'stashd_pat_' . bin2hex(random_bytes(24));
        $record = $this->tokens->create(
            userId: PrefixedUlid::parse((string) $user->id),
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
        return array_map(
            static fn ($token): array => [
                'id' => (string) $token->id,
                'name' => $token->name,
                'token_preview' => $token->tokenPreview,
                'scopes' => $token->scopesJson === null
                    ? []
                    : json_decode($token->scopesJson, true, flags: JSON_THROW_ON_ERROR),
                'last_used_at' => $token->lastUsedAt,
                'expires_at' => $token->expiresAt,
                'created_at' => $token->createdAt,
            ],
            $this->tokens->listForUser(PrefixedUlid::parse((string) $user->id)),
        );
    }

    public function revokeApiToken(UserRecord $user, PrefixedUlid $tokenId): void
    {
        $record = \App\Domain\Auth\ApiTokenRecord::findById(new \Tempest\Database\PrimaryKey($tokenId->toString()));

        if ($record === null || $record->userId !== (string) $user->id) {
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
