<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;
use NyonCode\WireModuleAuth\Tests\Fixtures\ResetUserPassword;

/*
 * Resetting a password from a code, end to end, the way a person does it.
 *
 * `ResetPasswordCodeTest` pins the seam, and reads the code off a recorder
 * wrapped round the codes service. This one takes nothing from inside: the code
 * is read out of the mail the transport was handed, the screens are posted in
 * the order the browser posts them, and the only proof of success accepted is
 * signing in.
 *
 * It exists because the token moved. The broker's token no longer rides in the
 * code's row; it is minted in the request that spends the code. A flow that is
 * put together differently underneath is the flow worth walking again from the
 * outside, including the paths that cross it — a second request, an address
 * that is not the one the code went to, a link-era token still lying about.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('auth.passwords.users.provider', 'users');
    config()->set('fortify.features', [Features::resetPasswords()]);
    config()->set('mail.default', 'array');

    CodeWorld::enable(['reset_password' => true]);

    app()->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
});

afterEach(function () {
    // Turning the flow on registers the code mail through Laravel's own static
    // `ResetPassword::toMailUsing()`, which outlives the application instance.
    // Left set, it is still there for the next file, which asserts it is not.
    ResetPassword::$toMailCallback = null;
});

/** Every mail the transport has been handed so far. */
function prcMails(): array
{
    return array_map(
        fn ($sent) => $sent->getOriginalMessage(),
        iterator_to_array(app('mailer')->getSymfonyTransport()->messages()),
    );
}

/**
 * The six digits in the latest mail to `$address`, as its reader sees them.
 *
 * Out of the rendered body rather than off any object: what a person has is
 * the mail, and a code that exists everywhere except in the text they read is
 * the failure this is looking for.
 */
function prcCodeFor(string $address): string
{
    $mails = array_values(array_filter(
        prcMails(),
        fn ($mail): bool => collect($mail->getTo())->contains(fn ($to): bool => $to->getAddress() === $address),
    ));

    expect($mails)->not->toBeEmpty();

    $body = strip_tags((string) (end($mails)->getHtmlBody() ?? end($mails)->getTextBody()));

    expect($body)->toMatch('/\b\d{3} \d{3}\b/');

    preg_match('/\b(\d{3}) (\d{3})\b/', $body, $digits);

    return $digits[1].$digits[2];
}

function prcReset(string $email, string $code, string $password): TestResponse
{
    return test()->from('/reset-password-code')->post('/reset-password-code', [
        'email' => $email,
        'code' => $code,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

function prcSignsIn(string $email, string $password): bool
{
    Auth::logout();
    test()->post('/login', ['email' => $email, 'password' => $password]);

    $in = Auth::check();
    Auth::logout();

    return $in;
}

// ─── The whole way round ───────────────────────────────────────────

it('takes a person from a forgotten password to signed in, with only the mail in hand', function () {
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com'])
        ->assertRedirect(route('wire-auth.reset-code'));

    // The screen the redirect lands on, with the address carried over so the
    // person types only the digits and a password.
    $this->get(route('wire-auth.reset-code'))
        ->assertOk()
        ->assertSee('value="ann@example.com"', false);

    prcReset('ann@example.com', prcCodeFor('ann@example.com'), 'a-brand-new-horse')
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors();

    expect(prcSignsIn('ann@example.com', 'a-brand-new-horse'))->toBeTrue()
        ->and(prcSignsIn('ann@example.com', 'correct-horse'))->toBeFalse()
        ->and(Hash::check('a-brand-new-horse', $user->fresh()->password))->toBeTrue();
});

it('leaves nothing behind that could reset the password a second time', function () {
    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);
    $code = prcCodeFor('ann@example.com');

    prcReset('ann@example.com', $code, 'a-brand-new-horse')->assertRedirect(route('login'));

    // Neither half survives: the code row is spent, and the token minted to
    // spend it was spent by Fortify in the same request.
    expect(DB::table('wire_auth_one_time_codes')->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);

    prcReset('ann@example.com', $code, 'a-third-horse')->assertSessionHasErrors('code');

    expect(prcSignsIn('ann@example.com', 'a-brand-new-horse'))->toBeTrue();
});

// ─── The paths that cross it ───────────────────────────────────────

it('holds the code to the address it was mailed to', function () {
    // The token is minted for whoever the posted address belongs to, so the
    // posted address had better be the one the code went to. It is — the code
    // is looked up by it — and this is the test that says so from outside.
    $ann = CodeWorld::user();
    $bob = CodeWorld::user(['email' => 'bob@example.com']);

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    prcReset('bob@example.com', prcCodeFor('ann@example.com'), 'taken-over-horse')
        ->assertSessionHasErrors('code');

    expect(Hash::check('correct-horse', $bob->fresh()->password))->toBeTrue()
        ->and(Hash::check('correct-horse', $ann->fresh()->password))->toBeTrue();
});

it('honours only the latest code when somebody asks twice', function () {
    // A second request is the ordinary case — the first mail was slow, or in
    // spam — and it replaces the first code rather than adding a second live
    // one. The broker's own throttle is what stands between the two requests.
    config()->set('auth.passwords.users.throttle', 0);

    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);
    $first = prcCodeFor('ann@example.com');

    $this->post('/forgot-password', ['email' => 'ann@example.com']);
    $second = prcCodeFor('ann@example.com');

    expect(prcMails())->toHaveCount(2);

    if ($first !== $second) {
        prcReset('ann@example.com', $first, 'a-brand-new-horse')->assertSessionHasErrors('code');
    }

    prcReset('ann@example.com', $second, 'a-brand-new-horse')->assertRedirect(route('login'));

    expect(prcSignsIn('ann@example.com', 'a-brand-new-horse'))->toBeTrue();
});

it('does not care how the address was capitalised on either screen', function () {
    // People type their address the way they type it. The code is filed under
    // one spelling, and the account is found by another lookup entirely — the
    // two have to agree or the reset dies between them with a valid code.
    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    prcReset('Ann@Example.com', prcCodeFor('ann@example.com'), 'a-brand-new-horse')
        ->assertSessionHasNoErrors();

    expect(prcSignsIn('ann@example.com', 'a-brand-new-horse'))->toBeTrue();
});

it('keeps the token out of every row it writes, from the request to the reset', function () {
    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    // What Laravel files for the reset is a hash; what the codes table files is
    // a hash of the digits and nothing else. At no point on the way is there a
    // string in either table that Fortify's reset would accept as it stands.
    $stored = DB::table('password_reset_tokens')->where('email', 'ann@example.com')->value('token');
    $row = DB::table('wire_auth_one_time_codes')->first();

    expect($stored)->toStartWith('$2y$')
        ->and((string) $row->payload)->not->toContain($stored)
        ->and(json_decode((string) ($row->payload ?? 'null'), true) ?? [])->not->toHaveKey('token');

    // A wrong attempt counts, and still writes nothing readable.
    prcReset('ann@example.com', prcCodeFor('ann@example.com') === '000000' ? '111111' : '000000', 'x-horse-x-horse');

    $row = DB::table('wire_auth_one_time_codes')->first();
    expect($row->attempts)->toBe(1)
        ->and(json_decode((string) ($row->payload ?? 'null'), true) ?? [])->not->toHaveKey('token');
});

it('mails a code the link screen cannot use', function () {
    // The mail carries digits and no link, and Fortify's own link screen takes
    // a token. So the digits are not a token under another name: posted where a
    // token goes, they reset nothing.
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->from('/reset-password')->post('/reset-password', [
        'token' => prcCodeFor('ann@example.com'),
        'email' => 'ann@example.com',
        'password' => 'a-brand-new-horse',
        'password_confirmation' => 'a-brand-new-horse',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue();
});
