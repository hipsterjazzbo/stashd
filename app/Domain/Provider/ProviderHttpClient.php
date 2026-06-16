<?php

declare(strict_types=1);

namespace App\Domain\Provider;

use Stringable;
use Tempest\Support\Uri\Uri;

interface ProviderHttpClient
{
    public function get(Uri|string|Stringable $url): ProviderHttpResponse;
}
