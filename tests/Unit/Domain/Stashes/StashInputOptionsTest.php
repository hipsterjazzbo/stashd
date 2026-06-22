<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Stashes;

use App\Providers\InputOption;
use App\Providers\InputOptionType;
use App\Stashes\StashInputOptions;

test('fromArray returns null when nothing was chosen', function (): void {
    expect(StashInputOptions::fromArray(null))->toBeNull()
        ->and(StashInputOptions::fromArray([]))->toBeNull()
        ->and(StashInputOptions::fromArray(['provider' => []]))->toBeNull();
});

test('fromArray accepts both camelCase and snake_case title regex keys', function (): void {
    $camel = StashInputOptions::fromArray(['titleRegexInclude' => 'foo']);
    $snake = StashInputOptions::fromArray(['title_regex_include' => 'foo']);

    expect($camel?->titleRegexInclude)->toBe('foo')
        ->and($snake?->titleRegexInclude)->toBe('foo');
});

test('fromArray keeps provider option keys exactly as given', function (): void {
    $options = StashInputOptions::fromArray(['provider' => ['include_shorts' => false, 'include_live' => true]]);

    expect($options?->provider)->toBe(['include_shorts' => false, 'include_live' => true]);
});

test('fromArray drops non-scalar and non-string-keyed provider values', function (): void {
    $options = StashInputOptions::fromArray(['provider' => [0 => true, 'quality' => 'high', 'bad' => ['nested']]]);

    expect($options?->provider)->toBe(['quality' => 'high']);
});

test('providerValue falls back to the option default when unset', function (): void {
    $option = new InputOption(key: 'include_shorts', label: 'Include Shorts', type: InputOptionType::Bool, default: true);
    $options = StashInputOptions::fromArray(['titleRegexInclude' => 'kept-for-non-null']);

    expect($options?->providerValue($option))->toBeTrue();
});

test('providerValue prefers the chosen value over the default', function (): void {
    $option = new InputOption(key: 'include_shorts', label: 'Include Shorts', type: InputOptionType::Bool, default: true);
    $options = StashInputOptions::fromArray(['provider' => ['include_shorts' => false]]);

    expect($options?->providerValue($option))->toBeFalse();
});

test('isValidTitleRegex accepts ordinary patterns and rejects malformed ones', function (): void {
    expect(StashInputOptions::isValidTitleRegex('^Episode \d+$'))->toBeTrue()
        ->and(StashInputOptions::isValidTitleRegex('(unterminated'))->toBeFalse();
});

test('isValidTitleRegex tolerates a pattern containing the delimiter character', function (): void {
    expect(StashInputOptions::isValidTitleRegex('a#b'))->toBeTrue();
});
