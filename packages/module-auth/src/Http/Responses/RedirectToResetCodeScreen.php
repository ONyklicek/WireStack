<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use NyonCode\WireModuleAuth\Http\Controllers\PasswordResetCodeController;

/**
 * Where "we have sent it" goes when what was sent is a code.
 *
 * Fortify's own response sends the person back to the form they just posted,
 * with a status line saying a link is on its way. That is right for a link — the
 * next thing they do is leave the browser — and wrong for a code, where the next
 * thing they do is come back to *this* tab and type six digits. Left alone, the
 * flow ends on a screen with no field for what the mail contains.
 *
 * Bound in place of Fortify's only while the reset-by-code flow is on, so an
 * installation that switched it off gets Fortify's answer unchanged.
 *
 * The address rides in the session rather than the query string: it is already
 * known, the screen fills it in so nobody retypes it, and a URL carrying
 * somebody's address is a URL that ends up in a log.
 */
final class RedirectToResetCodeScreen implements SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * Fortify constructs this with the broker's status key, and it is
     * deliberately not used: `passwords.sent` says a *link* was emailed, which
     * is the one sentence this flow has to stop saying.
     */
    public function __construct(string $status) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        /** @var Request $request */
        $address = (string) $request->input(Fortify::email(), '');

        return redirect()->route('wire-auth.reset-code')
            ->with(PasswordResetCodeController::SESSION_KEY, $address)
            ->with('status', __('wire-module-auth::messages.code_sent'));
    }
}
