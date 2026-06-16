<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vault;

use App\Services\Vault\VaultChecksum;

test('vault checksum formats and verifies sha256 digests', function (): void {
    $path = sys_get_temp_dir() . '/stashd-checksum-' . bin2hex(random_bytes(4));
    file_put_contents($path, 'stashd-checksum-fixture');
    $computed = VaultChecksum::computeFile($path);

    expect($computed)->toBe('sha256:' . hash('sha256', 'stashd-checksum-fixture'))
        ->and(VaultChecksum::verifyFile($path, $computed))->toBeTrue()
        ->and(VaultChecksum::verifyFile($path, 'sha256:' . str_repeat('0', 64)))->toBeFalse();

    unlink($path);
});

test('vault checksum verify passes when stored checksum is absent', function (): void {
    $path = sys_get_temp_dir() . '/stashd-checksum-' . bin2hex(random_bytes(4));
    file_put_contents($path, 'no-checksum-yet');

    expect(VaultChecksum::verifyFile($path, null))->toBeTrue();

    unlink($path);
});
