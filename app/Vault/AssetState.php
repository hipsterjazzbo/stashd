<?php

declare(strict_types=1);

namespace App\Vault;

enum AssetState: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Stale = 'stale';
    case Missing = 'missing';
    case Failed = 'failed';

    /** @return list<AssetState> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Failed],
            self::Processing => [self::Ready, self::Failed],
            self::Ready => [self::Stale, self::Missing, self::Processing],
            self::Stale => [self::Ready, self::Missing, self::Processing],
            self::Missing => [self::Ready, self::Processing, self::Failed],
            self::Failed => [self::Pending, self::Processing],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
