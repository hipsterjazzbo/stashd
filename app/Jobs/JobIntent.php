<?php

declare(strict_types=1);

namespace App\Jobs;

enum JobIntent: string
{
    case RoutineDiscovery = 'routine_discovery';
    case InitialBackfill = 'initial_backfill';
    case MetadataCapture = 'metadata_capture';
    case MetadataRefresh = 'metadata_refresh';
    case Download = 'download';
    case Enrich = 'enrich';
    case Broadcast = 'broadcast';
    case Repair = 'repair';
    case StorageCheck = 'storage_check';
    case Preflight = 'preflight';
    case CreateFromPreflight = 'create_from_preflight';
    case Boot = 'boot';
    case VerifyVault = 'verify_vault';
    case MediaServer = 'media_server';
}
