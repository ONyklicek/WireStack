<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesRoutes;
use NyonCode\WireModuleAuth\Http\Controllers\CodeLoginController;
use NyonCode\WireModuleAuth\Http\Controllers\EmailVerificationCodeController;
use NyonCode\WireModuleAuth\Http\Controllers\PasswordResetCodeController;
use NyonCode\WireModuleAuth\Http\Controllers\SecondFactorCodeController;
use NyonCode\WireModuleAuth\Support\Codes;

/**
 * The `auth-codes` group: the routes the four code flows need, and not one more.
 *
 * Placed by the application like every group (ADR 0041): `Route::wire('auth-codes')`
 * in routes/web.php, which the installer writes, or an `auth-codes` entry of
 * `wire-core.routes.groups`. Inside a group of the application's own, its
 * prefix, domain and middleware apply on top of these; the routes come back
 * keyed by name without the `wire-auth.` prefix — `['login-code']` — for
 * anything one of them needs alone. A named group is refused: the screens and
 * the mails link to these by name.
 *
 * **Every flow is behind its own switch**, which is the whole of ADR 0037 §3
 * expressed where it can be checked: an installation that turned nothing on has
 * no new lines in `route:list`, and a flow that is off cannot be reached by
 * guessing its URL.
 *
 * The middleware, guard and throttle are read from Fortify's config rather than
 * declared here, so these sit beside Fortify's own routes under whatever an
 * application configured — including a different guard, which `guest:` and
 * `auth:` both have to name to mean anything.
 */
final class CodeRoutes implements ProvidesRoutes
{
    public static function key(): string
    {
        return 'auth-codes';
    }

    public function defaults(): array
    {
        // The middleware, the guard and the throttle are Fortify's, read by the
        // group itself; there is nothing to add from outside.
        return [];
    }

    public function fixesNames(): bool
    {
        return true;
    }

    /** @return array<string, Route> */
    public function register(array $options): array
    {
        $guard = config('fortify.guard');
        $guest = 'guest:'.$guard;
        $auth = config('fortify.auth_middleware', 'auth').':'.$guard;

        // One limiter for every route that accepts digits or sends a mail. Named
        // after this package rather than reusing `fortify.limiters.login`,
        // because a wrong code is not a wrong password: the code dies on its own
        // after `codes.attempts`, and sharing a counter would let a mistyped
        // code lock somebody out of the password form they still know.
        $throttle = 'throttle:'.config('wire-module-auth.codes.throttle', '6,1');

        $routes = [];

        RouteFacade::group(['middleware' => config('fortify.middleware', ['web'])], function () use ($guest, $auth, $throttle, &$routes): void {
            if (Codes::login()) {
                $routes['login-code'] = RouteFacade::get('/login/code', [CodeLoginController::class, 'create'])
                    ->middleware($guest)
                    ->name('wire-auth.login-code');

                $routes['login-code.store'] = RouteFacade::post('/login/code', [CodeLoginController::class, 'store'])
                    ->middleware([$guest, $throttle])
                    ->name('wire-auth.login-code.store');

                $routes['login-code.challenge'] = RouteFacade::get('/login/code/challenge', [CodeLoginController::class, 'challenge'])
                    ->middleware($guest)
                    ->name('wire-auth.login-code.challenge');

                $routes['login-code.challenge.store'] = RouteFacade::post('/login/code/challenge', [CodeLoginController::class, 'verify'])
                    ->middleware([$guest, $throttle])
                    ->name('wire-auth.login-code.challenge.store');

                $routes['login-code.send'] = RouteFacade::post('/login/code/challenge/send', [CodeLoginController::class, 'send'])
                    ->middleware([$guest, $throttle])
                    ->name('wire-auth.login-code.send');
            }

            if (Codes::secondFactor()) {
                $routes['second-factor'] = RouteFacade::get('/two-factor/code', [SecondFactorCodeController::class, 'create'])
                    ->middleware($guest)
                    ->name('wire-auth.second-factor');

                $routes['second-factor.store'] = RouteFacade::post('/two-factor/code', [SecondFactorCodeController::class, 'store'])
                    ->middleware([$guest, $throttle])
                    ->name('wire-auth.second-factor.store');

                $routes['second-factor.send'] = RouteFacade::post('/two-factor/code/send', [SecondFactorCodeController::class, 'send'])
                    ->middleware([$guest, $throttle])
                    ->name('wire-auth.second-factor.send');
            }

            if (Codes::verifyEmail()) {
                // Behind `auth` and not `guest`: the person is signed in and stopped at
                // the one door that is inside the building.
                $routes['verify-email-code'] = RouteFacade::get('/email/verify/code', [EmailVerificationCodeController::class, 'create'])
                    ->middleware($auth)
                    ->name('wire-auth.verify-email-code');

                $routes['verify-email-code.store'] = RouteFacade::post('/email/verify/code', [EmailVerificationCodeController::class, 'store'])
                    ->middleware([$auth, $throttle])
                    ->name('wire-auth.verify-email-code.store');

                $routes['verify-email-code.send'] = RouteFacade::post('/email/verify/code/send', [EmailVerificationCodeController::class, 'send'])
                    ->middleware([$auth, $throttle])
                    ->name('wire-auth.verify-email-code.send');
            }

            if (Codes::resetPassword()) {
                // `/reset-password-code`, not `/reset-password/code`: Fortify serves
                // `/reset-password/{token}` and registers it first, so the tidier path
                // is swallowed by its token parameter — and the screen that answers is
                // the link one, with a token of "code" and no field for what the mail
                // actually contains.
                $routes['reset-code'] = RouteFacade::get('/reset-password-code', [PasswordResetCodeController::class, 'create'])
                    ->middleware($guest)
                    ->name('wire-auth.reset-code');

                $routes['reset-code.store'] = RouteFacade::post('/reset-password-code', [PasswordResetCodeController::class, 'store'])
                    ->middleware([$guest, $throttle])
                    ->name('wire-auth.reset-code.store');
            }
        });

        return $routes;
    }
}
