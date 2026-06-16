<?php

declare(strict_types=1);

/**
 * RoadRunner HTTP worker entrypoint.
 *
 * RoadRunner executes this script once per worker process and feeds PSR-7
 * requests over the Go↔PHP relay. See docs/runtime/roadrunner.md.
 */

use App\System\RoadRunner\TempestPsr7Bridge;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/tempest_internal_storage.php';

$root = dirname(__DIR__);

TempestPsr7Bridge::create($root, tempest_internal_storage())->run();
