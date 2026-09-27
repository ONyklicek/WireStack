<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WirePanels\Contracts\HasTenants;

/**
 * A tenant zone that cannot work as declared (ADR 0040).
 *
 * Every case is a declaration the application wrote, caught on the first
 * request or at route registration — never a request quietly let into a
 * tenant, or out of one, by a guess.
 */
final class TenancyConfigurationException extends InvalidArgumentException implements WireException
{
    public static function noTenantModel(): self
    {
        return new self(
            '`wire-core.tenancy.model` names no Eloquent model. A tenant zone finds its tenant by that '
            .'model\'s route key, so it has to say which model a tenant is.'
        );
    }

    public static function noParameter(string $route): self
    {
        return new self(
            "The route [{$route}] carries the wire.tenant middleware and no {tenant} parameter. "
            .'Put it in the group\'s prefix (`app/{tenant}`) or its domain (`{tenant}.example.com`).'
        );
    }

    public static function userCannotHaveTenants(string $user): self
    {
        return new self(
            "[{$user}] signed into a tenant zone and does not implement ".HasTenants::class.'. '
            .'Membership is refused rather than assumed: without the contract nothing says which '
            .'tenants this person may enter.'
        );
    }

    public static function unknownMode(string $zone, string $mode): self
    {
        return new self("The zone [{$zone}] sets `tenant` to [{$mode}]; it takes 'path' or 'domain'.");
    }

    public static function domainModeWithoutDomain(string $zone): self
    {
        return new self(
            "The zone [{$zone}] puts the tenant in the domain and names no `domain` to put it in front of."
        );
    }
}
