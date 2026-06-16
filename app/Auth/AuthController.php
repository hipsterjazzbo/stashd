<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\Support\PrefixedUlid;
use Tempest\Http\Request;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Delete;
use Tempest\Router\Get;
use Tempest\Router\Post;
use Tempest\Router\WithMiddleware;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class AuthController
{
    public function __construct(
        private AuthService $auth,
        private AuthContext $context,
    ) {
    }

    #[Post('/api/v1/auth/setup')]
    public function setup(Request $request): Json
    {
        if (! $this->auth->isSetupRequired()) {
            return new Json([
                'error' => [
                    'code' => 'setup_already_completed',
                    'message' => 'Owner account already exists.',
                ],
            ], Status::CONFLICT);
        }

        $body = $request->body;
        $email = trim((string) ($body['email'] ?? ''));
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $username === '' || $password === '') {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'email, username, and password are required.',
                ],
            ], Status::BAD_REQUEST);
        }

        try {
            $user = $this->auth->setupOwner($email, $username, $password);
        } catch (SetupAlreadyCompleted) {
            return new Json([
                'error' => [
                    'code' => 'setup_already_completed',
                    'message' => 'Owner account already exists.',
                ],
            ], Status::CONFLICT);
        }

        return new Json([
            'user' => $this->userPayload($user),
            'setup_required' => false,
        ], Status::CREATED);
    }

    #[Post('/api/v1/auth/login')]
    public function login(Request $request): Json
    {
        $body = $request->body;
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'email and password are required.',
                ],
            ], Status::BAD_REQUEST);
        }

        try {
            $user = $this->auth->login($email, $password);
        } catch (SetupRequired) {
            return new Json([
                'error' => [
                    'code' => 'setup_required',
                    'message' => 'Create the owner account before logging in.',
                ],
            ], Status::FORBIDDEN);
        } catch (InvalidCredentials) {
            return new Json([
                'error' => [
                    'code' => 'invalid_credentials',
                    'message' => 'Invalid email or password.',
                ],
            ], Status::UNAUTHORIZED);
        }

        return new Json(['user' => $this->userPayload($user)]);
    }

    #[Post('/api/v1/auth/logout')]
    public function logout(): Json
    {
        $this->auth->logout();

        return new Json(['ok' => true]);
    }

    #[Get('/api/v1/auth/me')]
    public function me(): Json
    {
        $user = $this->context->requireUser();

        return new Json(['user' => $this->userPayload($user)]);
    }

    #[Post('/api/v1/auth/tokens')]
    public function createToken(Request $request): Json
    {
        $user = $this->context->requireUser();
        $body = $request->body;
        $name = trim((string) ($body['name'] ?? ''));

        if ($name === '') {
            return new Json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'name is required.',
                ],
            ], Status::BAD_REQUEST);
        }

        $scopes = isset($body['scopes']) && is_array($body['scopes']) ? $body['scopes'] : null;
        $expiresAt = isset($body['expires_at']) && is_string($body['expires_at']) ? $body['expires_at'] : null;

        $token = $this->auth->createApiToken($user, $name, $scopes, $expiresAt);

        return new Json($token, Status::CREATED);
    }

    #[Get('/api/v1/auth/tokens')]
    public function listTokens(): Json
    {
        $user = $this->context->requireUser();

        return new Json(['tokens' => $this->auth->listApiTokens($user)]);
    }

    #[Delete('/api/v1/auth/tokens/{tokenId}')]
    public function revokeToken(string $tokenId): Json
    {
        $user = $this->context->requireUser();
        $this->auth->revokeApiToken($user, PrefixedUlid::parse($tokenId));

        return new Json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function userPayload(UserRecord $user): array
    {
        return [
            'id' => (string) $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role->value,
        ];
    }
}
