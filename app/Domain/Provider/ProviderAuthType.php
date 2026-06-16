<?php

declare(strict_types=1);

namespace App\Domain\Provider;

enum ProviderAuthType: string
{
    case None = 'none';
    case ApiKey = 'api_key';
    case Oauth = 'oauth';
    case Cookies = 'cookies';
    case Session = 'session';
}
