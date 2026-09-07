<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Livewire\DeleteAccount;
use NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication;
use NyonCode\WireModuleUsers\Livewire\UpdatePassword;
use NyonCode\WireModuleUsers\Pages\CreateUser;
use NyonCode\WireModuleUsers\Pages\EditProfile;
use NyonCode\WireModuleUsers\Pages\EditUser;
use NyonCode\WireModuleUsers\Pages\ListUsers;
use NyonCode\WireModuleUsers\Pages\ViewUser;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Tests\Fixtures\AvatarUser;
use NyonCode\WireModuleUsers\Tests\Fixtures\SpatieOnlyUser;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\WireModuleUsersServiceProvider;
use Spatie\Permission\Models\Role;

/*
 * The module's pages, over a real users table.
 *
 * The assertions that matter are the two a user-management screen gets wrong:
 * what happens to the password field, and what happens to roles — which are not
 * a column and therefore cannot ride along with the save.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    // Spatie's own migration, in the shape its models expect.
    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('model_has_roles', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => Hash::make('secret-one')]);
    Role::create(['name' => 'editor', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
});

it('lists the application users', function () {
    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('jane@example.com');
});

it('never puts the password hash in the form state', function () {
    // The one that must not regress: the hash is in the record, and a page that
    // seeds it sends it to the browser inside the Livewire snapshot — and writes
    // it back re-hashed on the next save.
    $page = Livewire::test(EditUser::class, ['record' => 1]);

    expect($page->get('data.password') ?? null)->toBeNull()
        ->and($page->html())->not->toContain(User::first()->password);
});

it('keeps the current password when the field is left empty', function () {
    $before = User::first()->password;

    Livewire::test(EditUser::class, ['record' => 1])
        ->set('data.name', 'Jane Roe')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::first();

    expect($user->name)->toBe('Jane Roe')
        ->and($user->password)->toBe($before);
});

it('hashes a password that was typed', function () {
    Livewire::test(EditUser::class, ['record' => 1])
        ->set('data.password', 'brand-new-secret')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::first();

    expect($user->password)->not->toBe('brand-new-secret')
        ->and(Hash::check('brand-new-secret', $user->password))->toBeTrue();
});

it('creates a user with the roles that were picked', function () {
    Livewire::test(CreateUser::class)
        ->set('data.'.UserResource::field('name'), 'New Person')
        ->set('data.'.UserResource::field('email'), 'new@example.com')
        ->set('data.'.UserResource::field('password'), 'first-secret')
        ->set('data.roles', ['editor'])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'new@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('first-secret', $user->password))->toBeTrue()
        // Roles are a pivot, so they can only be written once the record exists —
        // which for a new user is after the save, not during it.
        ->and($user->roles->pluck('name')->all())->toBe(['editor']);
});

it('seeds the roles a user already has, and syncs what changed', function () {
    User::first()->syncRoles(['editor']);

    $page = Livewire::test(EditUser::class, ['record' => 1]);

    expect($page->get('data.roles'))->toBe(['editor']);

    $page->set('data.roles', ['admin'])->call('save')->assertHasNoErrors();

    expect(User::first()->roles->pluck('name')->all())->toBe(['admin']);
});

it('shows one user, with the roles they hold', function () {
    User::first()->syncRoles(['editor']);

    Livewire::test(ViewUser::class, ['record' => 1])
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('editor');
});

it('writes no roles when the save answered with something that is not a model', function () {
    // The guard in SyncsRoles. A form's `using()` may return anything — a DTO, an
    // id, nothing at all — and the after-save must not call syncRoles() on it.
    Livewire::test(UpUserWithCustomSave::class)
        ->set('data.'.UserResource::field('name'), 'Command Only')
        ->set('data.'.UserResource::field('email'), 'command@example.com')
        ->set('data.'.UserResource::field('password'), 'secret-xyz')
        ->set('data.roles', ['editor'])
        ->call('save')
        ->assertHasNoErrors();

    // Nothing was persisted by the framework, and nothing blew up on the way out.
    expect(User::where('email', 'command@example.com')->exists())->toBeFalse();
});

it('does not recognise a user model that took Spatie\'s trait instead of ours', function () {
    // Not a detection miss — a decision. These screens are built against
    // `nyoncode/laravel-permission-extended`: wildcard permissions, the
    // super-admin gate and the permission-change events are what the role forms
    // assume, and a management UI over half an authorization model is worse than
    // no management UI.
    config()->set('wire-module-users.model', SpatieOnlyUser::class);

    expect(Roles::hasExtendedPermissions())->toBeTrue()
        ->and(Roles::available())->toBeFalse()
        ->and(Roles::enabled())->toBeFalse();

    // And the application can still overrule the look, as it always could.
    config()->set('wire-module-users.roles', true);

    expect(Roles::enabled())->toBeTrue();
});

it('answers no when there is no user model to look at', function () {
    // An installation part-way through configuring itself: the model name is a
    // typo, or points at a class that has not been written yet. Answering "no
    // roles" beats a class-not-found on a page that was drawing a menu.
    config()->set('wire-module-users.roles', 'auto');
    config()->set('wire-module-users.model', 'App\\Models\\NotHere');

    expect(Roles::available())->toBeFalse();

    config()->set('wire-module-users.model', null);

    expect(Roles::available())->toBeFalse();
});

it('does not reach for roles at all when the installation has none', function () {
    config()->set('wire-module-users.roles', false);

    Livewire::test(EditUser::class, ['record' => 1])
        ->set('data.name', 'Jane Roeless')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::first()->name)->toBe('Jane Roeless');
});

it('reports the model it works over and whether roles are part of it', function () {
    $provider = new WireModuleUsersServiceProvider(app());

    // Every optional half is reported, not only the one that is on: an avatar
    // upload that never appears because a column is missing, and a two-factor
    // card that never appears because Fortify is not installed, are both
    // indistinguishable from a broken package until something says so.
    expect($provider->aboutData())->toBe([
        'User model' => User::class,
        'Roles' => 'enabled',
        'Avatars' => 'off',
        'Two-factor' => 'off',
        'Teams' => 'off',
    ]);
});

/** A page whose save is a command rather than a model write. */
class UpUserWithCustomSave extends CreateUser
{
    public function form(Form $form): Form
    {
        return parent::form($form)->using(static fn (array $data): string => 'handed to a command');
    }
}

/** A user model with no roles relation at all. */
class UpPlainUser extends Illuminate\Foundation\Auth\User
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}

class UpEditPlainUser extends EditUser
{
    protected function resolveRecord(): ?Model
    {
        return UpPlainUser::query()->find($this->record);
    }
}

it('seeds no roles for a record that has no such relation', function () {
    // A user model without the permission package's trait: the module still
    // works and the roles field is empty rather than fatal.
    expect(Livewire::test(UpEditPlainUser::class, ['record' => 1])->get('data.roles'))->toBe([]);
});

/* ── Your own account ─────────────────────────────────────────────────────── */

it('edits the signed-in user and nobody else', function () {
    // The security property, and the whole reason this is not the edit page with
    // a friendlier name: there is no id to change, so there is nothing to guess.
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'amelia@example.com', 'password' => Hash::make('secret')]);
    User::query()->create(['name' => 'Mason', 'email' => 'mason@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    Livewire::test(EditProfile::class)
        ->assertSet('data.name', 'Amelia')
        ->set('data.name', 'Amelia Stone')
        ->call('save');

    expect($me->refresh()->name)->toBe('Amelia Stone')
        ->and(User::query()->where('email', 'mason@example.com')->value('name'))->toBe('Mason');
});

it('refuses to be a profile page for nobody', function () {
    // Asked of the page rather than of a render, because a render wraps whatever
    // it catches in a ViewException and the guarantee being asserted is this
    // method's. The route should sit behind `auth`; if it does not, this is the
    // difference between a redirect and a page that edits nothing while looking
    // like it worked.
    expect(fn () => (new EditProfile)->resolveRecord())
        ->toThrow(AuthenticationException::class);
});

it('gives nobody a way to put themselves in a role', function () {
    Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $me = User::query()->create(['name' => 'Amelia', 'email' => 'roles@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    // Removed from the schema, not merely ignored on save: a field that is
    // rendered and then discarded is one refactor away from being honoured.
    //
    // Flattened, and that is the assertion. Over the top-level schema this read
    // "the first level holds no field called roles", which the day the resource
    // grouped its fields into sections was true of a form that rendered the
    // select one level down.
    $fields = Livewire::test(EditProfile::class)->instance()->form(app(Form::class))->getFlatComponents();

    expect(array_map(static fn (object $field): string => $field->getName(), $fields))
        ->not->toContain('roles');
});

it('has no password field at all, and saving leaves the hash alone', function () {
    // The field used to be here with "leave empty to keep the current password"
    // under it — an administrator's control, on the one screen where the
    // administrator is the account holder. It moved to UpdatePassword, which can
    // ask for the current password first; an admin editing somebody else has no
    // current password to give, which is why the two screens cannot share it.
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);
    $hash = $me->password;

    $this->be($me);

    $fields = Livewire::test(EditProfile::class)->instance()->form(app(Form::class))->getFlatComponents();

    expect(array_map(static fn (object $field): string => $field->getName(), $fields))
        ->not->toContain('password');

    Livewire::test(EditProfile::class)
        ->set('data.name', 'Amelia S.')
        ->call('save');

    expect($me->refresh()->password)->toBe($hash)
        ->and($me->name)->toBe('Amelia S.');
});

it('lists the cards this installation asked for, and no others', function () {
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    // Deleting your own account is off by default, and deliberately: in an admin
    // panel the person on this page is usually staff.
    expect(Livewire::test(EditProfile::class)->instance()->cards())
        ->toContain(UpdatePassword::class)
        ->not->toContain(DeleteAccount::class);

    config()->set('wire-module-users.profile.delete_account', true);
    config()->set('wire-module-users.profile.password', false);

    expect(Livewire::test(EditProfile::class)->instance()->cards())
        ->toContain(DeleteAccount::class)
        ->not->toContain(UpdatePassword::class);
});

it('shows no two-factor card without a Fortify feature to drive it', function () {
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    // The card is on in config, and Fortify is installed with its two-factor
    // feature *not* enabled. Installed-but-off has to read as off — both
    // switches have to be on, or the card renders buttons that throw.
    config()->set('wire-module-users.profile.two_factor', true);

    expect(Livewire::test(EditProfile::class)->instance()->cards())
        ->not->toContain(TwoFactorAuthentication::class);
});

it('is titled after the person, not after the resource', function () {
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('secret')]);

    $this->be($me);

    expect(Livewire::test(EditProfile::class)->instance()->getTitle())
        ->toBe(__('wire-module-users::messages.profile'));
});

it('is routed before the record page, or it would be looked up as one', function () {
    // `users/profile` and `users/{record}` are the same URL shape, and the order
    // in pages() is what decides which one wins.
    $keys = array_keys(UserResource::pages());

    expect(array_search('profile', $keys, true))->toBeLessThan(array_search('view', $keys, true));
});

it('shows no roles for a user model that has no such relation', function () {
    // `'roles' => true` overrules the look — which is documented, and is how a
    // model with no `roles()` at all reaches the screens that read one. The
    // detail page renders with an empty list rather than fataling on the
    // relation.
    config()->set('wire-module-users.roles', true);
    config()->set('wire-module-users.model', AvatarUser::class);

    expect(Roles::enabled())->toBeTrue();

    $user = AvatarUser::query()->create([
        'name' => 'Amelia',
        'email' => 'no-relation@example.com',
        'password' => Hash::make('secret'),
    ]);

    Livewire::test(ViewUser::class, ['record' => $user->getKey()])
        ->assertOk()
        ->assertSee(__('wire-module-users::messages.no_roles'));
});
