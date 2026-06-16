<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

enum BroadcastItemState: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Stale = 'stale';
    case Failed = 'failed';
    case Disabled = 'disabled';

    /** @return list<BroadcastItemState> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Disabled],
            self::Processing => [self::Ready, self::Stale, self::Failed],
            self::Ready => [self::Processing, self::Stale, self::Disabled],
            self::Stale => [self::Processing, self::Ready, self::Failed],
            self::Failed => [self::Processing],
            self::Disabled => [self::Pending, self::Processing],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
