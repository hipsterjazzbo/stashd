<?php

declare(strict_types=1);

namespace App\Services\State;

use App\Domain\Broadcast\BroadcastItemRecord;
use App\Domain\Broadcast\BroadcastItemState;
use App\Domain\Broadcast\BroadcastRecord;
use App\Domain\Broadcast\BroadcastState;
use App\Domain\Broadcast\BroadcastTriggerRecord;
use App\Domain\Broadcast\BroadcastTriggerRunRecord;
use App\Domain\Broadcast\BroadcastTriggerRunState;
use App\Domain\Broadcast\BroadcastTriggerState;
use App\Domain\Command\CommandRecord;
use App\Domain\Command\CommandState;
use App\Domain\Job\JobRecord;
use App\Domain\Job\JobState;
use App\Domain\Media\AssetRecord;
use App\Domain\Media\AssetState;
use App\Domain\Media\MediaItemRecord;
use App\Domain\Media\MediaItemState;
use App\Domain\MediaServer\MediaServerConnectionRecord;
use App\Domain\MediaServer\MediaServerConnectionState;
use App\Domain\Stash\StashInputRecord;
use App\Domain\Stash\StashInputState;
use App\Domain\Stash\StashItemRecord;
use App\Domain\Stash\StashItemState;
use App\Domain\Stash\StashRecord;
use App\Domain\Stash\StashState;
use App\Infrastructure\Persistence\RecordTimestamps;

final readonly class StateTransitionService
{
    public function transitionCommand(CommandRecord $record, CommandState $next): CommandRecord
    {
        return $this->apply($record, $record->state, $next, 'Command');
    }

    public function transitionJob(JobRecord $record, JobState $next): JobRecord
    {
        return $this->apply($record, $record->state, $next, 'Job');
    }

    public function transitionStash(StashRecord $record, StashState $next): StashRecord
    {
        return $this->apply($record, $record->state, $next, 'Stash');
    }

    public function transitionStashInput(StashInputRecord $record, StashInputState $next): StashInputRecord
    {
        return $this->apply($record, $record->state, $next, 'Stash input');
    }

    public function transitionStashItem(StashItemRecord $record, StashItemState $next): StashItemRecord
    {
        return $this->apply($record, $record->state, $next, 'Stash item');
    }

    public function transitionMediaItem(MediaItemRecord $record, MediaItemState $next): MediaItemRecord
    {
        return $this->apply($record, $record->state, $next, 'Media item');
    }

    public function transitionAsset(AssetRecord $record, AssetState $next): AssetRecord
    {
        return $this->apply($record, $record->state, $next, 'Asset');
    }

    public function transitionBroadcast(BroadcastRecord $record, BroadcastState $next): BroadcastRecord
    {
        return $this->apply($record, $record->state, $next, 'Broadcast');
    }

    public function transitionBroadcastItem(BroadcastItemRecord $record, BroadcastItemState $next): BroadcastItemRecord
    {
        return $this->apply($record, $record->state, $next, 'Broadcast item');
    }

    public function transitionMediaServerConnection(
        MediaServerConnectionRecord $record,
        MediaServerConnectionState $next,
    ): MediaServerConnectionRecord {
        return $this->apply($record, $record->state, $next, 'Media server connection');
    }

    public function transitionBroadcastTrigger(
        BroadcastTriggerRecord $record,
        BroadcastTriggerState $next,
    ): BroadcastTriggerRecord {
        return $this->apply($record, $record->state, $next, 'Broadcast trigger');
    }

    public function transitionBroadcastTriggerRun(
        BroadcastTriggerRunRecord $record,
        BroadcastTriggerRunState $next,
    ): BroadcastTriggerRunRecord {
        return $this->apply($record, $record->state, $next, 'Broadcast trigger run');
    }

    /**
     * @template TState of object
     * @template TRecord of object{state: TState, updatedAt: ?string, save(): void}
     *
     * @param TRecord $record
     * @param TState $current
     * @param TState $next
     *
     * @return TRecord
     */
    private function apply(object $record, object $current, object $next, string $entity): object
    {
        if (! method_exists($current, 'canTransitionTo') || ! $current->canTransitionTo($next)) {
            throw InvalidStateTransition::forEntity(
                $entity,
                $this->stringifyState($current),
                $this->stringifyState($next),
            );
        }

        $record->state = $next;
        $record->updatedAt = RecordTimestamps::now();
        $record->save();

        return $record;
    }

    private function stringifyState(object $state): string
    {
        return $state instanceof \BackedEnum ? $state->value : (string) $state;
    }
}
