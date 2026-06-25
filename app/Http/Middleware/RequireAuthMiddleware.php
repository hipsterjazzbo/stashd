<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AuthContext;
use App\Auth\AuthService;
use Tempest\Http\Request;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\HttpMiddleware;
use Tempest\Router\HttpMiddlewareCallable;

final readonly class RequireAuthMiddleware implements HttpMiddleware
{
    /** @var list<string> */
    private const array PUBLIC_PATHS = [
        '/health',
        '/api/v1/auth/setup',
        '/api/v1/auth/login',
    ];

    public function __construct(
        private AuthService $auth,
        private AuthContext $context,
    ) {
    }

    public function __invoke(Request $request, HttpMiddlewareCallable $next): Json|\Tempest\Http\Response
    {
        try {
            $path = parse_url($request->path, PHP_URL_PATH) ?: $request->path;

            if (in_array($path, self::PUBLIC_PATHS, true)) {
                return $next($request);
            }

            if ($this->auth->isSetupRequired()) {
                return new Json([
                    'error' => [
                        'code' => 'setup_required',
                        'message' => 'Create the admin account before using the API.',
                    ],
                ], Status::FORBIDDEN);
            }

            $user = $this->auth->resolveFromRequest($request);

            if ($user === null) {
                return new Json([
                    'error' => [
                        'code' => 'authentication_required',
                        'message' => 'Authentication required.',
                    ],
                ], Status::UNAUTHORIZED);
            }

            $this->context->set($user);

            return $next($request);
        } finally {
            $this->context->set(null);
        }
    }
}
