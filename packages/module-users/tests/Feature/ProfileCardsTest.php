<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Contracts\HasAvatar;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Livewire\DeleteAccount;
use NyonCode\WireModuleUsers\Livewire\UpdatePassword;
use NyonCode\WireModuleUsers\Pages\ViewUser;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Avatars;
use NyonCode\WireModuleUsers\Support\TwoFactor;
use NyonCode\WireModuleUsers\Tests\Fixtures\AvatarUser;

/*
 * The cards the profile page is made of, each one on its own.
 *
 * They are separate components rather than sections of one form because they
 * ask separate questions, and these tests are the shape of that: a password
 * change has to prove who you are, and a deletion has to be asked for twice.
 */

beforeEach(function () {
    // One model for the whole file, and it is the one with a face: these cards
    // are about the person signed in, and the avatar column is part of what that
    // page is. Roles are off — they are UserPagesTest's subject, and the pivot
    // tables they need have nothing to do with a password box.
    config()->set('wire-module-users.model', AvatarUser::class);
    config()->set('auth.providers.users.model', AvatarUser::class);
    config()->set('wire-module-users.roles', false);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('avatar_path')->nullable();
        $table->timestamps();
    });

    Avatars::flush();
});

afterEach(function () {
    Avatars::flush();
});

// ─── The password card ───────────────────────────────────────────────────────

it('changes a password when the current one is right', function () {
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('the-old-one')]);

    $this->be($me);

    Livewire::test(UpdatePassword::class)
        ->set('data.current_password', 'the-old-one')
        ->set('data.password', 'a-much-longer-new-one')
        ->set('data.password_confirmation', 'a-much-longer-new-one')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('a-much-longer-new-one', $me->refresh()->password))->toBeTrue();
});

it('refuses a change from somebody who cannot produce the current password', function () {
    // The whole reason this is not a fourth field on the profile form: a
    // password change has to prove you are the person whose password it is.
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('the-old-one')]);
    $hash = $me->password;

    $this->be($me);

    Livewire::test(UpdatePassword::class)
        ->set('data.current_password', 'not-it')
        ->set('data.password', 'a-much-longer-new-one')
        ->set('data.password_confirmation', 'a-much-longer-new-one')
        ->call('save')
        ->assertHasErrors('data.current_password');

    expect($me->refresh()->password)->toBe($hash);
});

it('refuses a new password that was typed differently twice', function () {
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('the-old-one')]);
    $hash = $me->password;

    $this->be($me);

    Livewire::test(UpdatePassword::class)
        ->set('data.current_password', 'the-old-one')
        ->set('data.password', 'a-much-longer-new-one')
        ->set('data.password_confirmation', 'a-much-longer-new-onf')
        ->call('save')
        ->assertHasErrors('data.password');

    expect($me->refresh()->password)->toBe($hash);
});

it('empties the boxes once it has saved, and keeps the session signed in', function () {
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('the-old-one')]);

    $this->be($me);

    Livewire::test(UpdatePassword::class)
        ->set('data.current_password', 'the-old-one')
        ->set('data.password', 'a-much-longer-new-one')
        ->set('data.password_confirmation', 'a-much-longer-new-one')
        ->call('save')
        ->assertSet('data.password', null)
        ->assertSet('data.current_password', null);

    // AuthenticateSession compares the session's copy of the hash against the
    // user's on every request. Without moving it along, changing your password
    // signs you out on the very next click.
    expect(session('password_hash_'.Auth::getDefaultDriver()))
        ->toBe($me->refresh()->getAuthPassword());
});

it('says so once, not twice', function () {
    // The save runtime already notifies. An override of save() that sent its own
    // message put two toasts on screen for one save, which is the bug this names
    // the message to avoid.
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('the-old-one')]);

    $this->be($me);

    $sent = [];
    NotificationManager::setDefaultDriver(new class($sent) implements NotificationDriver
    {
        public function __construct(public array &$sent) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification->message;
        }
    });

    Livewire::test(UpdatePassword::class)
        ->set('data.current_password', 'the-old-one')
        ->set('data.password', 'a-much-longer-new-one')
        ->set('data.password_confirmation', 'a-much-longer-new-one')
        ->call('save');

    NotificationManager::reset();

    expect($sent)->toBe([__('wire-module-users::messages.password_saved')]);
});

// ─── The delete-account card ─────────────────────────────────────────────────

it('deletes an account only after the password is typed into the dialog', function () {
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    Livewire::test(DeleteAccount::class)
        ->call('confirm')
        ->assertSet('confirming', true)
        ->set('data.password', 'secret')
        ->call('delete');

    expect(AvatarUser::query()->find($me->getKey()))->toBeNull()
        ->and(Auth::check())->toBeFalse();
});

it('keeps an account whose owner cannot produce its password', function () {
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    Livewire::test(DeleteAccount::class)
        ->call('confirm')
        ->set('data.password', 'not-it')
        ->call('delete')
        ->assertHasErrors('data.password');

    expect(AvatarUser::query()->find($me->getKey()))->not->toBeNull()
        ->and(Auth::check())->toBeTrue();
});

it('clears the typed password when the dialog is dismissed', function () {
    // A dialog reopened by a mis-click should not already hold a password.
    $me = AvatarUser::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    Livewire::test(DeleteAccount::class)
        ->call('confirm')
        ->set('data.password', 'secret')
        ->call('cancel')
        ->assertSet('confirming', false)
        ->assertSet('data.password', null);
});

// ─── Avatars ─────────────────────────────────────────────────────────────────

it('offers the upload only where there is a column to put it in', function () {
    expect(Avatars::enabled())->toBeTrue();

    // Flattened, because the form groups its fields into sections: the top of
    // the schema is three headings, and the field is one level under one of them.
    $names = array_map(
        static fn (object $field): string => $field->getName(),
        (new UserResource)->form(app(Form::class))->getFlatComponents(),
    );

    expect($names)->toContain('avatar_path');

    // The same application without the column: no field, rather than a field
    // over a column that is not there.
    Schema::dropColumns('users', ['avatar_path']);
    Avatars::flush();

    expect(Avatars::enabled())->toBeFalse();

    $names = array_map(
        static fn (object $field): string => $field->getName(),
        (new UserResource)->form(app(Form::class))->getFlatComponents(),
    );

    expect($names)->not->toContain('avatar_path');
});

it('re-answers when the application points it at a different model', function () {
    // A single memoised flag was wrong the moment anything asked before the
    // application had finished pointing the module at its own user model —
    // `php artisan about` does — and the cached answer was "no such class, so no
    // avatars" for the rest of the request.
    config()->set('wire-module-users.model', 'App\\Models\\NotHere');

    expect(Avatars::available())->toBeFalse();

    config()->set('wire-module-users.model', AvatarUser::class);

    expect(Avatars::available())->toBeTrue();
});

it('answers off when the application says off, without looking at the schema', function () {
    config()->set('wire-module-users.avatar.enabled', false);

    expect(Avatars::enabled())->toBeFalse();

    config()->set('wire-module-users.avatar.enabled', true);

    expect(Avatars::enabled())->toBeTrue();
});

it('turns a stored path into a URL, and leaves one that already is alone', function () {
    Storage::fake('public');

    $me = AvatarUser::query()->create([
        'name' => 'Amelia',
        'email' => 'a@example.com',
        'password' => Hash::make('secret'),
        'avatar_path' => 'avatars/amelia.png',
    ]);

    expect($me->getAvatarUrl())->toContain('avatars/amelia.png')
        ->and($me)->toBeInstanceOf(HasAvatar::class);

    // An application that fills the same column from Gravatar or an identity
    // provider gets its picture drawn without overriding anything.
    $me->avatar_path = 'https://example.com/amelia.png';

    expect($me->getAvatarUrl())->toBe('https://example.com/amelia.png');

    $me->avatar_path = null;

    expect($me->getAvatarUrl())->toBeNull();
});

// ─── Two-factor ──────────────────────────────────────────────────────────────

it('reports no two-factor when the Fortify feature is not switched on', function () {
    // Installed-but-off and not-installed-at-all have to read the same, or the
    // card appears and its buttons throw.
    expect(TwoFactor::available())->toBeFalse()
        ->and(TwoFactor::enabled())->toBeFalse()
        ->and(TwoFactor::confirmed(Auth::user()))->toBeFalse()
        ->and(TwoFactor::pending(Auth::user()))->toBeFalse();
});

it('lets an application answer the two-factor question for itself', function () {
    config()->set('wire-module-users.two_factor', true);

    expect(TwoFactor::enabled())->toBeTrue();

    config()->set('wire-module-users.two_factor', false);

    expect(TwoFactor::enabled())->toBeFalse();
});

it('leads the read-only page with the picture, where there is a column for one', function () {
    // The same switch the form field is behind: an application without the
    // column gets a detail page with no empty avatar frame on it.
    $me = AvatarUser::query()->create([
        'name' => 'Amelia',
        'email' => 'a@example.com',
        'password' => Hash::make('secret'),
        'avatar_path' => 'avatars/amelia.jpg',
    ]);

    $this->be($me);

    expect(Livewire::test(ViewUser::class, ['record' => $me->getKey()])->html())
        ->toContain('avatars/amelia.jpg');

    Schema::dropColumns('users', ['avatar_path']);
    Avatars::flush();

    expect(Livewire::test(ViewUser::class, ['record' => $me->getKey()])->html())
        ->not->toContain('avatars/amelia.jpg');
});
