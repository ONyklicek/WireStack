<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing\Contracts;

/**
 * A page that only makes sense inside a tenant — a company's profile, its
 * members.
 *
 * Routed only in a group that carries `{tenant}`, in its prefix or its domain,
 * and skipped everywhere else: an application that does not use tenancy, or a
 * zone of one that is not a tenant zone, never gets an address that could only
 * ever answer 404 (ADR 0040, ADR 0041).
 */
interface RequiresTenant {}
