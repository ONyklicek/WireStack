<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Pages\EditProfile;
use NyonCode\WireModuleUsers\Support\EmailVerification;
use NyonCode\WireModuleUsers\Tests\Fixtures\VerifyingUser;
use NyonCode\WireModuleUsers\Tests\Support\Access;
use NyonCode\WireModuleUsers\WireModuleUsersServiceProvider;

/*
 * A verified flag belongs to the address it was granted for.
 *
 * Fortify's `UpdateUserProfileInformation` clears it when the address changes;
 * this module replaces that action with its own form, and for a while the
 * promise did not come with it — somebody could type an address they did not
 * control and stay flagged verified on it.
 *
 * The rule lives on the model rather than in a form hook, so what is asserted
 * here is the record's behaviour, reached both through a screen and directly.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
    });

    config()->set('wire-module-users.model', VerifyingUser::class);
    config()->set('auth.providers.users.model', VerifyingUser::class);
    config()->set('wire-module-users.roles', false);

    // The listeners are attached at boot, to whatever model config named then —
    // which in an application is the only moment that matters, and in a test is
    // before `beforeEach` got a say. Re-registering is how this repo re-reads
    // boot-time config (see CodeWorld::enable in wire-module-auth).
    app()->register(WireModuleUsersServiceProvider::class, force: true);

    Notification::fake();
});

function verifiedUser(): VerifyingUser
{
    return VerifyingUser::query()->create([
        'name' => 'Amelia',
        'email' => 'amelia@example.com',
        'password' => Hash::make('x'),
        'email_verified_at' => now(),
    ]);
}

it('forgets the verified flag when the address changes', function () {
    $user = verifiedUser();

    $user->forceFill(['email' => 'somewhere-else@example.com'])->save();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('leaves the flag alone on a save that does not touch the address', function () {
    $user = verifiedUser();

    $user->forceFill(['name' => 'Amelia B'])->save();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->name)->toBe('Amelia B');
});

it('stands aside when the same save sets the flag itself', function () {
    // Verified a while ago, so the stamp written below is a real change. The
    // guard is `isDirty`, which cannot tell "set to the value it already had"
    // from "not touched at all" — and every case this is for (a seeder, a
    // backfill, an admin marking an address verified) writes a new stamp or
    // fills a null, both of which are dirty.
    $user = VerifyingUser::query()->create([
        'name' => 'Amelia',
        'email' => 'amelia@example.com',
        'password' => Hash::make('x'),
        'email_verified_at' => now()->subDay(),
    ]);

    // Explicit intent wins; the rule only fills the silence. Overruling it here
    // would make "this address is verified" impossible to say in one save.
    $user->forceFill([
        'email' => 'known-good@example.com',
        'email_verified_at' => now(),
    ])->save();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->email)->toBe('known-good@example.com');
});

it('asks for the new address to be proven', function () {
    $user = verifiedUser();

    $user->forceFill(['email' => 'somewhere-else@example.com'])->save();

    Notification::assertSentTo(
        $user->fresh(),
        VerifyEmail::class,
    );
});

it('can be switched off by an application that clears the flag its own way', function () {
    config()->set('wire-module-users.reverify_on_email_change', false);

    $user = verifiedUser();

    // The listeners are registered at boot, so the switch is read there as well
    // as here — this asserts the support class agrees, which is what the boot
    // check reads.
    expect(EmailVerification::resetsOnChange())->toBeFalse()
        ->and(EmailVerification::applies($user->fill(['email' => 'x@example.com'])))->toBeFalse();
});

it('clears the flag through the profile screen, not just through the model', function () {
    $user = verifiedUser();

    $this->be($user);
    Access::grantEveryAbility();

    Livewire::test(EditProfile::class)
        ->set('data.email', 'typed-by-hand@example.com')
        ->call('save');

    expect($user->fresh()->email)->toBe('typed-by-hand@example.com')
        ->and($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('registers nothing when the rule is switched off', function () {
    config()->set('wire-module-users.reverify_on_email_change', false);

    $user = verifiedUser();

    app()->register(WireModuleUsersServiceProvider::class, force: true);

    $user->forceFill(['email' => 'somewhere-else@example.com'])->save();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('registers nothing when the configured model is not a model', function () {
    // A misconfigured `wire-module-users.model` — a class that does not exist,
    // or one that is not Eloquent — must not fatal at boot. The screens report
    // that separately; this only has to decline.
    config()->set('wire-module-users.model', 'App\\Models\\NotHere');

    app()->register(WireModuleUsersServiceProvider::class, force: true);
})->throwsNoExceptions();
