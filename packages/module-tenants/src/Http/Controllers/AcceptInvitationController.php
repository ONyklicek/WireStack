<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use NyonCode\WireModuleTenants\Actions\AcceptInvitation;
use NyonCode\WireModuleTenants\Models\TenantInvitation;
use NyonCode\WireModuleTenants\Support\Registration;

/**
 * The link in an invitation e-mail. Signed, so an edited id is refused before
 * this runs; then open, and for the address of the person following it — a
 * forwarded link is a 403 for whoever it was forwarded to.
 */
final class AcceptInvitationController
{
    public function __invoke(Request $request, TenantInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof Model, 403);

        $tenant = (new AcceptInvitation)($invitation, $user);

        abort_if($tenant === null, 403, __('wire-module-tenants::messages.invitation_invalid'));

        return redirect()->to(Registration::homeOf((string) $tenant->getRouteKey()));
    }
}
