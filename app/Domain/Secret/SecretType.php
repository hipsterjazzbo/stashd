<?php

declare(strict_types=1);

namespace App\Domain\Secret;

enum SecretType: string
{
    case ApiKey = 'api_key';
    case OauthToken = 'oauth_token';
    case Password = 'password';
    case BroadcastToken = 'broadcast_token';
    case MediaServerToken = 'media_server_token';
    case Generic = 'generic';
}
