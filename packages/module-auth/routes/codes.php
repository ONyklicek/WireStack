<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireModuleAuth\Http\Controllers\CodeLoginController;
use NyonCode\WireModuleAuth\Http\Controllers\EmailVerificationCodeController;
use NyonCode\WireModuleAuth\Http\Controllers\PasswordResetCodeController;
use NyonCode\WireModuleAuth\Http\Controllers\SecondFactorCodeController;
use NyonCode\WireModuleAuth\Support\Codes;

/*
 * The routes the four code flows need, and not one more.
 *
 * **Every group is behind its own switch**, which is the whole of ADR 0037 §3
 * expressed where it can be checked: an installation that turned nothing on has
 * no new lines in `route:list`, and a flow that is off cannot be reached by
 * guessing its URL.
 *
 * The group's middleware, guard and throttle are read from Fortify's config
 * rather than declared here, so these sit beside Fortify's own routes under
 * whatever an application configured — including a different guard, which
 * `guest:` and `auth:` both have to name to mean anything.
 */

$guard = config('fortify.guard');
$guest = 'guest:'.$guard;
$auth = config('fortify.auth_middleware', 'auth').':'.$guard;

// One limiter for every route that accepts digits or sends a mail. Named after
// this package rather than reusing `fortify.limiters.login`, because a wrong
// code is not a wrong password: the code dies on its own after
// `codes.attempts`, and sharing a counter would let a mistyped code lock
// somebody out of the password form they still know.
$throttle = 'throttle:'.config('wire-module-auth.codes.throttle', '6,1');

Route::group(['middleware' => config('fortify.middleware', ['web'])], function () use ($guest, $auth, $throttle): void {
    if (Codes::login()) {
        Route::get('/login/code', [CodeLoginController::class, 'create'])
            ->middleware($guest)
            ->name('wire-auth.login-code');

        Route::post('/login/code', [CodeLoginController::class, 'store'])
            ->middleware([$guest, $throttle])
            ->name('wire-auth.login-code.store');

        Route::get('/login/code/challenge', [CodeLoginController::class, 'challenge'])
            ->middleware($guest)
            ->name('wire-auth.login-code.challenge');

        Route::post('/login/code/challenge', [CodeLoginController::class, 'verify'])
            ->middleware([$guest, $throttle])
            ->name('wire-auth.login-code.challenge.store');

        Route::post('/login/code/challenge/send', [CodeLoginController::class, 'send'])
            ->middleware([$guest, $throttle])
            ->name('wire-auth.login-code.send');
    }

    if (Codes::secondFactor()) {
        Route::get('/two-factor/code', [SecondFactorCodeController::class, 'create'])
            ->middleware($guest)
            ->name('wire-auth.second-factor');

        Route::post('/two-factor/code', [SecondFactorCodeController::class, 'store'])
            ->middleware([$guest, $throttle])
            ->name('wire-auth.second-factor.store');

        Route::post('/two-factor/code/send', [SecondFactorCodeController::class, 'send'])
            ->middleware([$guest, $throttle])
            ->name('wire-auth.second-factor.send');
    }

    if (Codes::verifyEmail()) {
        // Behind `auth` and not `guest`: the person is signed in and stopped at
        // the one door that is inside the building.
        Route::get('/email/verify/code', [EmailVerificationCodeController::class, 'create'])
            ->middleware($auth)
            ->name('wire-auth.verify-email-code');

        Route::post('/email/verify/code', [EmailVerificationCodeController::class, 'store'])
            ->middleware([$auth, $throttle])
            ->name('wire-auth.verify-email-code.store');

        Route::post('/email/verify/code/send', [EmailVerificationCodeController::class, 'send'])
            ->middleware([$auth, $throttle])
            ->name('wire-auth.verify-email-code.send');
    }

    if (Codes::resetPassword()) {
        // `/reset-password-code`, not `/reset-password/code`: Fortify serves
        // `/reset-password/{token}` and registers it first, so the tidier path
        // is swallowed by its token parameter — and the screen that answers is
        // the link one, with a token of "code" and no field for what the mail
        // actually contains.
        Route::get('/reset-password-code', [PasswordResetCodeController::class, 'create'])
            ->middleware($guest)
            ->name('wire-auth.reset-code');

        Route::post('/reset-password-code', [PasswordResetCodeController::class, 'store'])
            ->middleware([$guest, $throttle])
            ->name('wire-auth.reset-code.store');
    }
});
