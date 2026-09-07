<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Support\Screens;

/*
 * What this installation's sign-in surface has.
 *
 * Every answer here decides whether a link is drawn, and a link drawn for a
 * route Fortify never registered is a 404 an application finds out about from a
 * user. So each one is asked of the switch that creates the route, rather than
 * of a copy of it kept here.
 */

it('reads the registration, reset, verification and two-factor switches', function () {
    // The suite turns every feature on; this is the shape an application with
    // Laravel's own defaults is in.
    expect(Screens::canRegister())->toBeTrue()
        ->and(Screens::canResetPassword())->toBeTrue()
        ->and(Screens::mustVerifyEmail())->toBeTrue()
        ->and(Screens::hasTwoFactor())->toBeTrue();
});

it('answers no to every one of them where the features are off', function () {
    config()->set('fortify.features', []);

    expect(Screens::canRegister())->toBeFalse()
        ->and(Screens::canResetPassword())->toBeFalse()
        ->and(Screens::mustVerifyEmail())->toBeFalse()
        ->and(Screens::hasTwoFactor())->toBeFalse();
});

it('answers each one from its own switch, not from one flag for all four', function () {
    // A single boolean behind all four would pass the two tests above and be
    // wrong for every application that enables some of them.
    config()->set('fortify.features', [Features::resetPasswords()]);

    expect(Screens::canResetPassword())->toBeTrue()
        ->and(Screens::canRegister())->toBeFalse()
        ->and(Screens::mustVerifyEmail())->toBeFalse()
        ->and(Screens::hasTwoFactor())->toBeFalse();
});

it('finds the route to sign out through', function () {
    expect(Screens::canSignOut())->toBeTrue();
});

it('says there is none when the application took Fortify routes over', function () {
    // `Fortify::ignoreRoutes()` leaves the naming to the application. A form
    // posting to a route that does not exist is a 500 in the chrome of every
    // page, so the entry has to be able to draw nothing.
    app('router')->setRoutes(new RouteCollection);

    expect(Screens::canSignOut())->toBeFalse();
});
