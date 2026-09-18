<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;
use NyonCode\WireModuleAuth\Tests\Fixtures\RecordingCodes;
use NyonCode\WireModuleAuth\Tests\Fixtures\ResetUserPassword;

/*
 * A forgotten password, reset by code, in a real browser — the pilot.
 *
 * The same flow `verify-auth-reset-code.mjs` drives over CDP, written as a Pest
 * browser test to see what the difference is worth (see
 * `architecture/plans/pest-browser-pilot.md`). What the driver has to work
 * around, this one does not have at all:
 *
 *   - its own database, not the shared seeded one, so there is no demo account
 *     to borrow and no password to put back afterwards;
 *   - the code is read off the codes service, not parsed out of `laravel.log`;
 *   - the flow's preconditions are PHP — a config switch, a user, a binding —
 *     instead of whatever the workbench happens to have set.
 *
 * What only a browser can say is still said by a browser: the six boxes are an
 * Alpine controller, and a code typed into them has to reach the hidden input
 * the native form posts, or the reset submits an empty code.
 */

beforeEach(function () {
    CodeWorld::migrate();

    // The shell's frame with scripts in it. The markup tests' frame is a title
    // and a slot; a browser needs Livewire, Alpine and the controllers.
    CodeWorld::frame();
    View::prependNamespace('wire-admin', __DIR__.'/../Fixtures/browser-views');

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('auth.passwords.users.provider', 'users');
    config()->set('fortify.features', [Features::resetPasswords()]);
    config()->set('mail.default', 'array');

    CodeWorld::enable(['reset_password' => true]);

    $this->codes = CodeWorld::recordCodes();

    app()->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
});

afterEach(function () {
    ResetPassword::$toMailCallback = null;
});

/** The digits the last mail carried. */
function brCode(RecordingCodes $codes): string
{
    return $codes->last->code;
}

it('draws the reset screen with its controllers running', function () {
    CodeWorld::user();

    visit('/forgot-password')
        ->assertPresent('@auth-forgot-form')
        ->assertNoJavaScriptErrors()
        ->assertScript('typeof window.Alpine', 'object');
});

it('takes a person from a forgotten password to signed in', function () {
    $user = CodeWorld::user();

    $page = visit('/forgot-password')
        ->type('email', 'ann@example.com')
        ->press('@auth-submit')
        ->assertPathIs('/reset-password-code')
        ->assertValue('input[name="email"]', 'ann@example.com');

    // Key by key into the first box, the way a person types it from the mail:
    // the controller moves focus from box to box, and the input the form posts
    // is hidden behind them. (`type()` sets the whole value in one go, which is
    // neither typing nor pasting, and the controller rightly keeps two digits.)
    $page->typeSlowly('@form-otp-code-0', brCode($this->codes), 20)
        ->assertValue('input[name="code"]', brCode($this->codes))
        ->type('input[name="password"]', 'a-brand-new-horse')
        ->type('input[name="password_confirmation"]', 'a-brand-new-horse')
        ->press('@auth-submit')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();

    expect(Hash::check('a-brand-new-horse', $user->fresh()->password))->toBeTrue();
});

it('refuses a wrong code on the screen it was typed on, and resets nothing', function () {
    $user = CodeWorld::user();

    visit('/forgot-password')
        ->type('email', 'ann@example.com')
        ->press('@auth-submit')
        ->assertPathIs('/reset-password-code')
        ->typeSlowly('@form-otp-code-0', $wrong = brCode($this->codes) === '000000' ? '111111' : '000000', 20)
        ->assertValue('input[name="code"]', $wrong)
        ->type('input[name="password"]', 'a-brand-new-horse')
        ->type('input[name="password_confirmation"]', 'a-brand-new-horse')
        ->press('@auth-submit')
        ->assertPathIs('/reset-password-code')
        ->assertSee(__('wire-module-auth::messages.code_invalid'));

    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue()
        ->and(Auth::check())->toBeFalse();
});
