<?php

declare(strict_types=1);

namespace App\Providers\YouTube;

interface YouTubeDataApiKeyResolver
{
    public function key(): ?string;

    public function hasKey(): bool;
}
