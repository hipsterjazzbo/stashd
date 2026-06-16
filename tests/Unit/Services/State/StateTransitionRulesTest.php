<?php

declare(strict_types=1);

namespace Tests\Unit\Services\State;

use App\Domain\Command\CommandState;
use App\Domain\Job\JobState;
use App\Domain\Media\MediaItemState;
use App\Domain\Stash\StashInputState;
use App\Domain\Stash\StashItemState;
use App\Domain\Stash\StashState;

test('command state allows accepted to running and completed path', function (): void {
    expect(CommandState::Accepted->canTransitionTo(CommandState::Running))->toBeTrue()
        ->and(CommandState::Running->canTransitionTo(CommandState::Completed))->toBeTrue()
        ->and(CommandState::Completed->canTransitionTo(CommandState::Running))->toBeFalse();
});

test('job state allows pending to processing to ready', function (): void {
    expect(JobState::Pending->canTransitionTo(JobState::Processing))->toBeTrue()
        ->and(JobState::Processing->canTransitionTo(JobState::Ready))->toBeTrue()
        ->and(JobState::Ready->canTransitionTo(JobState::Pending))->toBeFalse();
});

test('stash lifecycle allows ready to failed and back', function (): void {
    expect(StashState::Ready->canTransitionTo(StashState::Failed))->toBeTrue()
        ->and(StashState::Failed->canTransitionTo(StashState::Ready))->toBeTrue()
        ->and(StashState::Ready->canTransitionTo(StashState::Disabled))->toBeTrue();
});

test('stash input and item states allow recovery from failure', function (): void {
    expect(StashInputState::Ready->canTransitionTo(StashInputState::Disabled))->toBeTrue()
        ->and(StashInputState::Disabled->canTransitionTo(StashInputState::Ready))->toBeTrue()
        ->and(StashItemState::Active->canTransitionTo(StashItemState::Hidden))->toBeTrue()
        ->and(StashItemState::Hidden->canTransitionTo(StashItemState::Active))->toBeTrue();
});

test('media item state allows metadata and download transitions', function (): void {
    expect(MediaItemState::Discovered->canTransitionTo(MediaItemState::MetadataReady))->toBeTrue()
        ->and(MediaItemState::MetadataReady->canTransitionTo(MediaItemState::DownloadPending))->toBeTrue()
        ->and(MediaItemState::Downloading->canTransitionTo(MediaItemState::Ready))->toBeTrue()
        ->and(MediaItemState::Ready->canTransitionTo(MediaItemState::Discovered))->toBeFalse();
});
