<?php

declare(strict_types=1);

namespace Tests\Unit\System\RoadRunner;

use Tempest\Http\Cookie\CookieManager;
use Tests\IntegrationTestCase;

/**
 * Proves the mechanism TempestPsr7Bridge::run() relies on to stop CookieManager
 * (a Tempest #[Singleton]) from leaking cookie state between requests on the
 * same long-lived RoadRunner worker: see app/System/RoadRunner/TempestPsr7Bridge.php.
 */
final class CookieManagerWorkerResetTest extends IntegrationTestCase
{
    public function test_resolving_twice_returns_the_same_singleton_instance(): void
    {
        $first = $this->container->get(CookieManager::class);
        $second = $this->container->get(CookieManager::class);

        $this->assertSame($first, $second);
    }

    public function test_unregister_forces_a_fresh_empty_instance_on_next_resolution(): void
    {
        $original = $this->container->get(CookieManager::class);
        $original->add(new \Tempest\Http\Cookie\Cookie(key: 'stashd_session', value: 'leftover'));

        $this->container->unregister(CookieManager::class);

        $fresh = $this->container->get(CookieManager::class);

        $this->assertNotSame($original, $fresh);
        $this->assertSame([], $fresh->all());
    }
}
