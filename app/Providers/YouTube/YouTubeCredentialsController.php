<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

use App\Http\Api\ApiJson;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Routing\AllowApiClients;
use App\System\Secret\SecretsService;
use App\System\Secret\SecretType;
use Tempest\Http\Request;
use Tempest\Http\Responses\Json;
use Tempest\Http\Status;
use Tempest\Router\Get;
use Tempest\Router\Put;
use Tempest\Router\WithMiddleware;

use function Tempest\Support\str;

#[AllowApiClients]
#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class YouTubeCredentialsController
{
    public function __construct(
        private YouTubeDataApiKeyResolver $dataApiKey,
        private SecretsService $secrets,
    ) {
    }

    #[Get('/api/v1/providers/youtube/credentials')]
    public function show(): Json
    {
        return new Json(['configured' => $this->dataApiKey->hasKey()]);
    }

    #[Put('/api/v1/providers/youtube/credentials')]
    public function update(Request $request): Json
    {
        $body = ApiJson::normalizeRequest($request->body);
        $apiKey = str((string) ($body['api_key'] ?? $body['apiKey'] ?? ''))->trim()->toString();

        if ($apiKey === '') {
            return $this->validationError('api_key is required.');
        }

        $this->secrets->put(SecretsBackedYouTubeDataApiKeyResolver::SECRET_KEY, SecretType::ApiKey, $apiKey);

        return new Json(['configured' => true]);
    }

    private function validationError(string $message): Json
    {
        return new Json([
            'error' => [
                'code' => 'validation_error',
                'message' => $message,
            ],
        ], Status::BAD_REQUEST);
    }
}
