<?php

declare(strict_types=1);

namespace App\Commands;

enum CommandType: string
{
    case StashSync = 'stash.sync';
    case StashBackfill = 'stash.backfill';
    case ItemRefreshMetadata = 'item.refresh_metadata';
    case BroadcastPlan = 'broadcast.plan';
    case BroadcastRebuild = 'broadcast.rebuild';
    case BroadcastVerify = 'broadcast.verify';
    case BroadcastPrune = 'broadcast.prune';
    case BroadcastTrigger = 'broadcast.trigger';
    case BroadcastRotateToken = 'broadcast.rotate_token';
    case StashPreflight = 'stash.preflight';
    case StashCreateFromPreflight = 'stash.create_from_preflight';
    case SystemPruneTemp = 'system.prune_temp';
    case SystemBoot = 'system.boot';
    case SystemStorageCheck = 'system.storage_check';
    case ItemDownload = 'item.download';
    case AssetVerify = 'asset.verify';
    case SystemVerifyVault = 'system.verify_vault';
    case MediaServerTestConnection = 'media_server.test_connection';
    case MediaServerListLibraries = 'media_server.list_libraries';
}
