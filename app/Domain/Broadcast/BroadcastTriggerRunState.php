<?php

declare(strict_types=1);

namespace App\Domain\Broadcast;

enum BroadcastTriggerRunState: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    /** @return list<BroadcastTriggerRunState> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Failed],
            self::Processing => [self::Ready, self::Failed],
            self::Ready, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
