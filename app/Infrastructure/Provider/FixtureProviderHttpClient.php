<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Provider\ProviderHttpClient;
use App\Domain\Provider\ProviderHttpResponse;
use Stringable;

use function Tempest\Support\str;

use Tempest\Support\Uri\Uri;

final readonly class FixtureProviderHttpClient implements ProviderHttpClient
{
    /** @param array<string, string> $map */
    public function __construct(
        private string $fixturesDirectory,
        private array $map = [],
    ) {
    }

    public function get(Uri|string|Stringable $url): ProviderHttpResponse
    {
        $uri = $url instanceof Uri ? $url : Uri::from((string) $url);
        $requestUrl = $uri->toString();
        $fixture = $this->map[$requestUrl] ?? null;

        if ($fixture === null) {
            foreach ($this->map as $pattern => $file) {
                if (str($requestUrl)->startsWith($pattern)) {
                    $fixture = $file;
                    break;
                }
            }
        }

        if ($fixture === null) {
            return new ProviderHttpResponse(404, '');
        }

        $path = str($this->fixturesDirectory)->rtrim('/')->append('/')->append(str($fixture)->ltrim('/')->toString())->toString();

        if (! is_file($path)) {
            return new ProviderHttpResponse(404, '');
        }

        $body = file_get_contents($path);
        $body = is_string($body) ? $body : '';

        if (str($fixture)->contains('unavailable')) {
            return new ProviderHttpResponse(404, $body);
        }

        return new ProviderHttpResponse(200, $body);
    }
}
