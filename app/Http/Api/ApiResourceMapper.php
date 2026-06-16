<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Domain\Broadcast\BroadcastItemRecord;
use App\Domain\Broadcast\BroadcastRecord;
use App\Domain\Command\CommandRecord;
use App\Domain\Job\JobRecord;
use App\Domain\Media\AssetRecord;
use App\Domain\Media\MediaItemRecord;
use App\Domain\MediaServer\MediaServerConnectionRecord;

final class ApiResourceMapper
{
    /** @return array<string, mixed> */
    public static function command(CommandRecord $command): array
    {
        return ApiJson::encode([
            'id' => (string) $command->id,
            'type' => $command->type->value,
            'state' => $command->state->value,
            'targetType' => $command->targetType,
            'targetId' => $command->targetId,
            'options' => self::decodeJson($command->optionsJson),
            'result' => self::decodeJson($command->resultJson),
            'createdByUserId' => $command->createdByUserId,
            'createdAt' => $command->createdAt,
            'updatedAt' => $command->updatedAt,
        ]);
    }

    /** @return array<string, mixed> */
    public static function job(JobRecord $job): array
    {
        return ApiJson::encode([
            'id' => (string) $job->id,
            'commandId' => $job->commandId,
            'intent' => $job->intent->value,
            'entityType' => $job->entityType,
            'entityId' => $job->entityId,
            'state' => $job->state->value,
            'priority' => $job->priority,
            'attempts' => $job->attempts,
            'maxAttempts' => $job->maxAttempts,
            'scheduledAt' => $job->scheduledAt,
            'startedAt' => $job->startedAt,
            'finishedAt' => $job->finishedAt,
            'heartbeatAt' => $job->heartbeatAt,
            'progressCurrent' => $job->progressCurrent,
            'progressTotal' => $job->progressTotal,
            'progressPercent' => $job->progressPercent,
            'progressLabel' => $job->progressLabel,
            'lastError' => $job->lastError,
            'payload' => self::decodeJson($job->payloadJson),
            'createdAt' => $job->createdAt,
            'updatedAt' => $job->updatedAt,
        ]);
    }

    /** @return array<string, mixed> */
    public static function broadcast(BroadcastRecord $broadcast): array
    {
        return ApiJson::encode([
            'id' => (string) $broadcast->id,
            'stashId' => $broadcast->stashId,
            'type' => $broadcast->type->value,
            'name' => $broadcast->name,
            'slug' => $broadcast->slug,
            'state' => $broadcast->state->value,
            'settings' => self::decodeJson($broadcast->settingsJson),
            'lastPlannedAt' => $broadcast->lastPlannedAt,
            'lastBuiltAt' => $broadcast->lastBuiltAt,
            'lastVerifiedAt' => $broadcast->lastVerifiedAt,
            'lastError' => $broadcast->lastError,
            'createdAt' => $broadcast->createdAt,
            'updatedAt' => $broadcast->updatedAt,
        ]);
    }

    /** @return array<string, mixed> */
    public static function broadcastItem(BroadcastItemRecord $item): array
    {
        return ApiJson::encode([
            'id' => (string) $item->id,
            'broadcastId' => $item->broadcastId,
            'stashItemId' => $item->stashItemId,
            'mediaItemId' => $item->mediaItemId,
            'state' => $item->state->value,
            'publishedPath' => $item->publishedPath,
            'publishedUri' => $item->publishedUri,
            'lastPublishedAt' => $item->lastPublishedAt,
            'lastVerifiedAt' => $item->lastVerifiedAt,
            'lastError' => $item->lastError,
            'createdAt' => $item->createdAt,
            'updatedAt' => $item->updatedAt,
        ]);
    }

    /** @return array<string, mixed> */
    public static function mediaServerConnection(MediaServerConnectionRecord $connection): array
    {
        return ApiJson::encode([
            'id' => (string) $connection->id,
            'type' => $connection->type->value,
            'name' => $connection->name,
            'baseUri' => $connection->baseUri,
            'state' => $connection->state->value,
            'settings' => self::decodeJson($connection->settingsJson),
            'lastCheckedAt' => $connection->lastCheckedAt,
            'lastError' => $connection->lastError,
            'createdAt' => $connection->createdAt,
            'updatedAt' => $connection->updatedAt,
        ]);
    }

    /** @return array<string, mixed> */
    public static function mediaItem(MediaItemRecord $item): array
    {
        return ApiJson::encode([
            'id' => (string) $item->id,
            'providerKey' => $item->providerKey,
            'providerItemId' => $item->providerItemId,
            'canonicalUri' => $item->canonicalUri,
            'title' => $item->title,
            'state' => $item->state->value,
            'durationSeconds' => $item->durationSeconds,
            'publishedAt' => $item->publishedAt,
            'thumbnailUri' => $item->thumbnailUri,
            'createdAt' => $item->createdAt,
            'updatedAt' => $item->updatedAt,
        ]);
    }

    /** @return array<string, mixed> */
    public static function asset(AssetRecord $asset): array
    {
        return ApiJson::encode([
            'id' => (string) $asset->id,
            'mediaItemId' => $asset->mediaItemId,
            'role' => $asset->role->value,
            'kind' => $asset->kind->value,
            'state' => $asset->state->value,
            'path' => $asset->path,
            'relativePath' => $asset->relativePath,
            'mimeType' => $asset->mimeType,
            'container' => $asset->container,
            'sizeBytes' => $asset->sizeBytes,
            'checksum' => $asset->checksum,
            'durationSeconds' => $asset->durationSeconds,
            'lastVerifiedAt' => $asset->lastVerifiedAt,
            'missingAt' => $asset->missingAt,
            'missingReason' => $asset->missingReason,
            'createdAt' => $asset->createdAt,
            'updatedAt' => $asset->updatedAt,
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function decodeJson(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? ApiJson::encode($decoded) : null;
    }
}
