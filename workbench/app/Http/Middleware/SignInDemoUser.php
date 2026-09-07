<?php

declare(strict_types=1);

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use NyonCode\WireModuleUsers\Support\Teams;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\User;

/**
 * The previews are a demo, and a demo with nobody signed in demonstrates the
 * wrong thing.
 *
 * Half of what the shell draws only exists for an authenticated user — the
 * corner with their name in it, the profile page, anything behind a policy — so
 * a preview server that browses as a guest shows a chrome with a hole in it, and
 * that hole is what was reported: *"nikde není viděl přihlášený uživatel"*.
 *
 * **Nothing here belongs in an application.** It signs in the first seeded user
 * and it is registered on the preview routes only — never on `web`, so the
 * screens `wire-module-auth` ships are still reachable at `/login` and are what
 * `verify-auth-screens.mjs` drives. An application gets there by signing in;
 * a preview that made you sign in first would be a preview of a login form.
 */
class SignInDemoUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $user = User::query()->orderBy('id')->first();

            if ($user !== null) {
                Auth::login($user);

                // The module's own `SetCurrentTeam` sits on the `web` group and
                // has therefore already run — *before* this, which is what makes
                // the workbench different from an application. There, the session
                // guard resolves the user inside the group and the team is set
                // for the whole request; here nobody was signed in yet, so the
                // registrar was told `null` and every role read came back empty.
                //
                // Nothing to fix in the middleware: a demo that signs somebody in
                // after the fact is the thing that has to say so.
                Teams::apply(Teams::currentId());
            }
        }

        return $next($request);
    }
}
