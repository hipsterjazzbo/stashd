<?php

declare(strict_types=1);

namespace App\Domain\Provider;

use Tempest\DateTime\DateTime;
use Tempest\DateTime\Timezone;

use function Tempest\Support\str;

final class ProviderDates
{
    public static function tryParse(?string $raw, ?Timezone $timezone = null): ?DateTime
    {
        if ($raw === null || str($raw)->trim()->isEmpty()) {
            return null;
        }

        try {
            return DateTime::parse(str($raw)->trim()->toString(), $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function utc(string $raw): DateTime
    {
        return DateTime::parse($raw, Timezone::UTC);
    }
}
