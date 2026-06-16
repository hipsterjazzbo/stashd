<?php

declare(strict_types=1);

namespace App\Stashes;

enum StashInputState: string
{
    case Ready = 'ready';
    case Failed = 'failed';
    case Disabled = 'disabled';

    /** @return list<StashInputState> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Ready => [self::Failed, self::Disabled],
            self::Failed, self::Disabled => [self::Ready],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
