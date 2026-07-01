<?php

declare(strict_types=1);

namespace App\Jobs;

enum JobIntent: string
{
    case InitialBackfill = 'initial_backfill';
    case Download = 'download';
    // No handler is registered for Enrich (see JobHandlerRegistryInitializer)
    // -- kept deliberately unwired as the fixture Phase2ExecutionTest uses to
    // prove the worker fails a job cleanly when no handler exists.
    case Enrich = 'enrich';
    case Broadcast = 'broadcast';
    case StorageCheck = 'storage_check';
    case Preflight = 'preflight';
    case AddInput = 'add_input';
    case Boot = 'boot';
    case VerifyVault = 'verify_vault';
    case MediaServer = 'media_server';
    case TranscodePodcastAudio = 'transcode_podcast_audio';
}
