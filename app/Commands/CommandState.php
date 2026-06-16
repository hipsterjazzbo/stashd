<?php

declare(strict_types=1);

namespace App\Commands;

enum CommandState: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /** @return list<CommandState> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Accepted => [self::Running, self::Rejected, self::Failed],
            self::Running => [self::Completed, self::Failed],
            self::Rejected, self::Completed, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
