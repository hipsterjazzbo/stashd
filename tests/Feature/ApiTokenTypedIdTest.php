<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\ApiTokenRepository;
use App\Auth\UserId;
use App\Auth\UserRepository;

test('ApiTokenRecord::userId round-trips as UserId through insert, where-lookup, and reload', function (): void {
    $users = $this->container->get(UserRepository::class);
    $tokens = $this->container->get(ApiTokenRepository::class);

    $user = $users->createAdmin(
        username: 'typed-id',
        passwordHash: password_hash('secret-password', PASSWORD_DEFAULT),
    );
    $userId = UserId::parse((string) $user->id);

    $created = $tokens->create(
        userId: $userId,
        name: 'typed-id-token',
        tokenHash: hash('sha256', 'typed-id-plain-token'),
        tokenPreview: 'typed...preview',
    );

    expect($created->userId)->toBeInstanceOf(UserId::class)
        ->and($created->userId->toString())->toBe($userId->toString());

    // WHERE-clause lookup: the property caster only fixes hydration, not raw bound
    // params, so this proves listForUser's explicit ->toString() binding works.
    $forUser = $tokens->listForUser($userId);
    expect($forUser)->toHaveCount(1)
        ->and($forUser[0]->userId->toString())->toBe($userId->toString());

    $reloaded = $tokens->findByHash(hash('sha256', 'typed-id-plain-token'));
    expect($reloaded?->userId)->toBeInstanceOf(UserId::class)
        ->and($reloaded?->userId->toString())->toBe($userId->toString());

    $resolvedUser = $users->findById($reloaded->userId);
    expect((string) $resolvedUser?->id)->toBe((string) $user->id);
});
