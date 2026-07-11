<?php

declare(strict_types=1);

namespace App\Auth;

enum ApiScope: string
{
    case ProfileRead = 'profile:read';
    case SystemRead = 'system:read';
    case JobsRead = 'jobs:read';
    case ActivityRead = 'activity:read';
    case MediaRead = 'media:read';
    case StashRead = 'stash:read';
    case StashWrite = 'stash:write';
    case BroadcastRead = 'broadcast:read';
    case BroadcastWrite = 'broadcast:write';
    case MediaServerRead = 'media-server:read';
    case MediaServerWrite = 'media-server:write';
    case CommandsCreate = 'commands:create';
    case TokensManage = 'tokens:manage';
}
