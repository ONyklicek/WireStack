<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/** A change to a company's members that would leave it in a state it must not be in. */
final class TenantMembershipException extends RuntimeException implements WireException
{
    public static function lastOwner(): self
    {
        return new self(__('wire-module-tenants::messages.last_owner'));
    }

    public static function notAllowed(): self
    {
        return new self(__('wire-module-tenants::messages.not_allowed'));
    }
}
