<?php

declare(strict_types=1);

namespace App\Domain\Stash;

enum StashItemState: string
{
    case Active = 'active';
    case Removed = 'removed';
    case Hidden = 'hidden';
    case Ignored = 'ignored';

    /** @return list<StashItemState> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Removed, self::Hidden, self::Ignored],
            self::Removed, self::Hidden, self::Ignored => [self::Active],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
