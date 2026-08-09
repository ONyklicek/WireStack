<?php

declare(strict_types=1);

namespace Laravel\Octane\Events;

/**
 * Stand-in for Octane's own `RequestTerminated`.
 *
 * `laravel/octane` is an optional dependency wire-core must not require, so the
 * provider registers its listener behind `class_exists()` — which means the one
 * deployment shape the listener exists for is the one shape the suite could never
 * enter. Declaring the symbol restores it.
 *
 * The file is deliberately not autoloadable (no PSR-4 mapping reaches this
 * namespace): the test that needs the listener requires it explicitly, before the
 * application — and with it the provider — boots.
 */
class RequestTerminated {}
