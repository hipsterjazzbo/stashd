<?php

declare(strict_types=1);

namespace App\Commands;

enum CommandType: string
{
    case BroadcastPlan = 'broadcast.plan';
    case BroadcastRebuild = 'broadcast.rebuild';
    case BroadcastVerify = 'broadcast.verify';
    case BroadcastPrune = 'broadcast.prune';
    case BroadcastTrigger = 'broadcast.trigger';
    case BroadcastRotateToken = 'broadcast.rotate_token';
    case StashPreflight = 'stash.preflight';
    case StashAddInput = 'stash.add_input';
    case SystemBoot = 'system.boot';
    case SystemStorageCheck = 'system.storage_check';
    case ItemDownload = 'item.download';
    case AssetVerify = 'asset.verify';
    case SystemVerifyVault = 'system.verify_vault';
    case MediaServerTestConnection = 'media_server.test_connection';
    case MediaServerListLibraries = 'media_server.list_libraries';
    case AssetTranscodePodcastAudio = 'asset.transcode_podcast_audio';
}
