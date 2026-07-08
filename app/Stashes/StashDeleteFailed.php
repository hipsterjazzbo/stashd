<?php

declare(strict_types=1);

namespace App\Stashes;

use RuntimeException;

final class StashDeleteFailed extends RuntimeException
{
    public function __construct(StashRecord $stash)
    {
        parent::__construct("Could not delete stash {$stash->id}: the database rolled back the change.");
    }
}
