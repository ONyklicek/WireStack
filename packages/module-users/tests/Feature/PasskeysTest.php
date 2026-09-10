<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Passkeys as LaravelPasskeys;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Livewire\PasskeyManagement;
use NyonCode\WireModuleUsers\Pages\EditProfile;
use NyonCode\WireModuleUsers\Support\Passkeys;
use NyonCode\WireModuleUsers\Tests\Fixtures\AvatarUser;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Access;

/*
 * The passkey card, over `laravel/passkeys`.
 *
 * The ceremony itself is the browser's and the package's — nothing here creates
 * a credential, and a test that faked one would be testing the fake. What this
 * card owns is the list, the delete, and knowing which of three states the
 * installation is in: feature off, feature on with the trait missing, and ready.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Artisan::call('migrate', [
        '--path' => realpath(__DIR__.'/../../../../vendor/laravel/passkeys/database/migrations'),
        '--realpath' => true,
    ]);

    config()->set('auth.providers.users.model', User::class);
    config()->set('wire-module-users.model', User::class);
    config()->set('fortify.features', [Features::passkeys()]);

    LaravelPasskeys::useUserModel(User::class);

    // Fortify registers its routes while it boots, off the feature list — which
    // this test sets afterwards. Re-registering runs that route file again, and
    // it is the only way a test meets the URLs the card's buttons post to.
    app()->register(FortifyServiceProvider::class, force: true);
});

/** Somebody signed in, with the trait unless the test asks otherwise. */
function passkeyUser(string $model = User::class): mixed
{
    $user = $model::query()->create([
        'name' => 'Ann Example',
        'email' => 'ann@example.com',
        'password' => bcrypt('secret'),
    ]);

    test()->actingAs($user);

    // Removing a key needs a confirmed password; the guard has its own test.
    Access::confirmPassword();

    return $user;
}

/** A credential row, as the package's own registration would have written it. */
function aPasskey(mixed $user, string $name = 'MacBook'): mixed
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => bin2hex(random_bytes(8)),
        'credential' => ['id' => 'stub'],
    ]);
}

it('is part of the profile page where the feature is on', function () {
    passkeyUser();

    expect(Passkeys::enabled())->toBeTrue()
        ->and((new EditProfile)->cards())->toContain(PasskeyManagement::class);
});

it('is absent where Fortify routes no passkeys', function () {
    // Installed is not enabled: the packages ship with Fortify, and a card whose
    // buttons post into a 404 is worse than no card.
    config()->set('fortify.features', []);

    expect(Passkeys::enabled())->toBeFalse()
        ->and((new EditProfile)->cards())->not->toContain(PasskeyManagement::class);
});

it('answers the config switch before it asks Fortify', function () {
    config()->set('wire-module-users.passkeys', false);
    expect(Passkeys::enabled())->toBeFalse();

    config()->set('wire-module-users.passkeys', true);
    config()->set('fortify.features', []);
    expect(Passkeys::enabled())->toBeTrue();
});

it('lists the keys somebody has, newest first', function () {
    $user = passkeyUser();

    aPasskey($user, 'MacBook');
    $this->travel(1)->minute();
    aPasskey($user, 'Phone');

    Livewire::test(PasskeyManagement::class)
        ->assertSee('data-testid="passkeys-list"', false)
        ->assertSeeInOrder(['Phone', 'MacBook'])
        ->assertDontSee('data-testid="passkeys-empty"', false);
});

it('says so when there are none yet', function () {
    passkeyUser();

    Livewire::test(PasskeyManagement::class)
        ->assertSee('data-testid="passkeys-empty"', false)
        // And still offers the way to add one.
        ->assertSee('data-testid="passkeys-add"', false);
});

it('names the missing trait rather than offering a button that achieves nothing', function () {
    // The one line an application writes itself, and the failure it produces is
    // silent: every route answers and the key belongs to nobody.
    config()->set('auth.providers.users.model', AvatarUser::class);
    passkeyUser(AvatarUser::class);

    Livewire::test(PasskeyManagement::class)
        ->assertSee('data-testid="passkeys-untraited"', false)
        ->assertDontSee('data-testid="passkeys-add"', false);
});

it('removes one through the package own action', function () {
    Event::fake([PasskeyDeleted::class]);

    $user = passkeyUser();
    $passkey = aPasskey($user);

    Livewire::test(PasskeyManagement::class)->call('forget', $passkey->getKey());

    expect($user->passkeys()->count())->toBe(0);

    // The package's event, so anything listening — an audit trail, a mail —
    // hears a key being removed here exactly as it would anywhere else.
    Event::assertDispatched(PasskeyDeleted::class);
});

it('will not remove somebody else key, whatever id arrives', function () {
    $stranger = User::query()->create([
        'name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('secret'),
    ]);
    $theirs = aPasskey($stranger);

    passkeyUser();

    // The id comes from the browser. Scoped by the query rather than trusted,
    // or anybody deletes anybody's passkey by typing a number.
    Livewire::test(PasskeyManagement::class)->call('forget', $theirs->getKey());

    expect($stranger->passkeys()->count())->toBe(1);
});

it('asks the database again when the browser registered one', function () {
    $user = passkeyUser();

    $component = Livewire::test(PasskeyManagement::class)->set('name', 'Phone');

    // The row was written by a request this component never saw — the passkeys
    // package's own — so the list is re-read rather than mutated here.
    aPasskey($user, 'Phone');

    $component->dispatch('wire-passkey-registered')
        ->assertSet('name', '')
        ->assertSee('Phone');
});

it('has nothing to forget on a model that cannot hold keys', function () {
    // The same branch the card draws its "trait missing" hint from, on the way
    // in rather than at render: a delete arriving for a model with no relation
    // must be a no-op, not a call into a relation that does not exist.
    config()->set('auth.providers.users.model', AvatarUser::class);
    passkeyUser(AvatarUser::class);

    Livewire::test(PasskeyManagement::class)->call('forget', 1);
})->throwsNoExceptions();

it('is targetable by a plugin, like every other card', function () {
    passkeyUser();

    expect((new PasskeyManagement)->hookKey())->toBe('users.passkeys');
});
