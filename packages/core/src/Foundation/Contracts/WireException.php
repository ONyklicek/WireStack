<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Throwable;

/**
 * Marks a failure as originating from wireStack rather than from PHP, Laravel or
 * application code.
 *
 * Every exception the stack throws implements this, so a consumer can catch the
 * whole stack in one clause:
 *
 * ```php
 * try {
 *     $table->getQuery();
 * } catch (WireException $e) {
 *     // a wire component was misconfigured
 * }
 * ```
 *
 * Implementations extend the SPL class that best describes the failure
 * (`InvalidArgumentException` for a bad argument, `RuntimeException` for a bad
 * state), so code that already catches those keeps working — adopting a domain
 * exception is never a breaking change for the caller.
 *
 * This is a marker: it adds no methods, because the only thing every wire
 * failure has in common is where it came from.
 */
interface WireException extends Throwable {}
