<?php

declare(strict_types=1);

namespace App\System\Wiring;

use App\Providers\YouTube\SecretsBackedYouTubeDataApiKeyResolver;
use App\Providers\YouTube\YouTubeDataApiKeyResolver;
use Tempest\Container\Container;
use Tempest\Container\Initializer;

final class YouTubeDataApiKeyResolverInitializer implements Initializer
{
    public function initialize(Container $container): YouTubeDataApiKeyResolver
    {
        return $container->get(SecretsBackedYouTubeDataApiKeyResolver::class);
    }
}
