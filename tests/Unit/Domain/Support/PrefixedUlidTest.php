<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Support;

use App\Domain\Support\PrefixedUlid;
use App\Domain\Support\PrefixedUlidGenerator;

test('prefixed ulid validates format', function (): void {
    $id = new PrefixedUlid('stash', '01ARZ3NDEKTSV4RRFFQ69G5FAV');

    expect((string) $id)->toBe('stash_01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->and(PrefixedUlid::isValid((string) $id))->toBeTrue()
        ->and(PrefixedUlid::parse((string) $id)->prefix)->toBe('stash');
});

test('prefixed ulid generator uses requested prefix', function (): void {
    $generator = new PrefixedUlidGenerator();
    $id = $generator->generate('cmd');

    expect($id->prefix)->toBe('cmd')
        ->and(PrefixedUlid::isValid($id->toString()))->toBeTrue();
});
