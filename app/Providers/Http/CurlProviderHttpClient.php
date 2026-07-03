<?php

declare(strict_types=1);

namespace App\Providers\Http;

use App\Providers\ProviderHttpClient;
use App\Providers\ProviderHttpResponse;
use App\Support\Http\CurlClient;
use RuntimeException;
use Stringable;
use Tempest\Support\Uri\Uri;

final readonly class CurlProviderHttpClient implements ProviderHttpClient
{
    public function get(Uri|string|Stringable $url): ProviderHttpResponse
    {
        $uri = $url instanceof Uri ? $url : Uri::from((string) $url);

        $response = CurlClient::send(
            method: 'GET',
            url: $uri->toString(),
            timeoutSeconds: 30,
            userAgent: 'Stashd/0.1 (+https://github.com/stashd/stashd)',
        );

        if ($response === null) {
            throw new RuntimeException('Unable to initialize HTTP client.');
        }

        return new ProviderHttpResponse(
            statusCode: $response['status'],
            body: $response['body'],
        );
    }
}
