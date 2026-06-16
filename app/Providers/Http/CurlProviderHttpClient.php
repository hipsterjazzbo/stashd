<?php

declare(strict_types=1);

namespace App\Providers\Http;

use App\Providers\ProviderHttpClient;
use App\Providers\ProviderHttpResponse;
use RuntimeException;
use Stringable;
use Tempest\Support\Uri\Uri;

final readonly class CurlProviderHttpClient implements ProviderHttpClient
{
    public function get(Uri|string|Stringable $url): ProviderHttpResponse
    {
        $uri = $url instanceof Uri ? $url : Uri::from((string) $url);
        $handle = curl_init($uri->toString());

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize HTTP client.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Stashd/0.1 (+https://github.com/stashd/stashd)',
        ]);

        $body = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new ProviderHttpResponse(
            statusCode: $statusCode > 0 ? $statusCode : 0,
            body: is_string($body) ? $body : '',
        );
    }
}
