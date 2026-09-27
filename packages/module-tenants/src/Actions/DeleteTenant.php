<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleTenants\Exceptions\TenantMembershipException;
use NyonCode\WireModuleTenants\Support\Membership;

/**
 * Delete a company — softly, by its owner, once they have typed its name.
 *
 * The name, rather than a yes/no: this is the one action in the module that
 * takes a whole company's work out of everybody's reach, and a confirmation
 * dialog is a button people press without reading.
 */
final class DeleteTenant
{
    public function __invoke(Model $tenant, string $confirmation, Model $actor): bool
    {
        if (! Membership::isOwner($tenant, $actor)) {
            throw TenantMembershipException::notAllowed();
        }

        if (trim($confirmation) !== (string) $tenant->getAttribute('name')) {
            return false;
        }

        $tenant->delete();

        return true;
    }
}
