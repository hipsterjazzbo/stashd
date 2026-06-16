<?php

declare(strict_types=1);

namespace App\System\State;

use App\Broadcasts\BroadcastItemRecord;
use App\Broadcasts\BroadcastItemState;
use App\Broadcasts\BroadcastRecord;
use App\Broadcasts\BroadcastState;
use App\Broadcasts\BroadcastTriggerRecord;
use App\Broadcasts\BroadcastTriggerRunRecord;
use App\Broadcasts\BroadcastTriggerRunState;
use App\Broadcasts\BroadcastTriggerState;
use App\Commands\CommandRecord;
use App\Commands\CommandState;
use App\Jobs\JobRecord;
use App\Jobs\JobState;
use App\MediaServers\MediaServerConnectionRecord;
use App\MediaServers\MediaServerConnectionState;
use App\Stashes\StashInputRecord;
use App\Stashes\StashInputState;
use App\Stashes\StashItemRecord;
use App\Stashes\StashItemState;
use App\Stashes\StashRecord;
use App\Stashes\StashState;
use App\Support\RecordTimestamps;
use App\Vault\AssetRecord;
use App\Vault\AssetState;
use App\Vault\MediaItemRecord;
use App\Vault\MediaItemState;

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
