<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage;

use App\Services\Storage\FilesystemProbe;

test('hardlink failure returns actionable error code', function (): void {
    $probe = new FilesystemProbe();
    $tmp = sys_get_temp_dir() . '/stashd-probe-' . bin2hex(random_bytes(4));
    mkdir($tmp);

    $result = $probe->probeHardlinkCrossRoot(
        sourceRoot: $tmp . '/vault',
        targetRoot: $tmp . '/broadcasts',
    );

    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe('storage_root_missing')
        ->and($result->message)->toContain('Vault is not writable');
});
