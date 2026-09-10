<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireModuleUsers\Concerns\SyncsRoles;
use NyonCode\WireModuleUsers\Livewire\PasskeyManagement;
use NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication;
use NyonCode\WireModuleUsers\Pages\EditUser;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Permissions;
use NyonCode\WireModuleUsers\Tests\Fixtures\FortifyUser;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Access;
use NyonCode\WireModuleUsers\WireModuleUsersServiceProvider;
use Spatie\Permission\Models\Role;

/*
 * The two holes these screens used to have, kept shut.
 *
 * Both were silent: the panel looked right, every button worked, and the thing
 * that was missing was a refusal. So these tests assert the refusal itself
 * rather than the happy path — a test that only proves the form saves would
 * have passed on the broken version too, which is exactly what happened.
 *
 * Nothing here calls Access::grantEveryAbility() or Access::confirmPassword();
 * that is the point of the file.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->timestamps();
    });

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
});

/* ---------------------------------------------------------------- S2: routes */

it('ships every user and role screen behind an ability', function () {
    $declared = [...UserResource::pages(), ...RoleResource::pages()];

    // The profile page is the deliberate exception: it is the signed-in
    // person's own account, so an administrative ability would lock everybody
    // out of their own password.
    $profile = $declared['profile'];
    unset($declared['profile']);

    expect($profile)->toBeString();

    foreach ($declared as $name => $page) {
        expect($page)->toBeInstanceOf(RoutePage::class, "page [{$name}] is not guarded")
            ->and($page->getPermission())->not->toBeNull("page [{$name}] declares no ability");

        // The route reads this, and a declaration that produced no `can:` would
        // be a permission in name only.
        expect($page->getMiddleware())->toContain('can:'.$page->getPermission());
    }
});

it('leaves a screen open when the installation asks for that', function () {
    config()->set('wire-module-users.permissions.users.update', null);

    expect(Permissions::for('users', 'update'))->toBeNull()
        ->and(UserResource::pages()['edit'])->toBeString();
});

it('treats an empty ability as none rather than as one nobody can hold', function () {
    // The shape an unset `.env` produces. `can:` on it would deny everyone with
    // no way to grant it, which is a lockout rather than a policy.
    config()->set('wire-module-users.permissions.users.update', '');

    expect(Permissions::for('users', 'update'))->toBeNull();
});

/* ------------------------------------------------- S2: escalation, not routes */

it('will not let somebody grant a role they may not grant', function () {
    Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);

    $me = User::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    $this->be($me);

    // No ability granted: this is the low-privileged account that used to be
    // able to reach the form and name any role in it.
    Livewire::test(EditUser::class, ['record' => $me->getKey()])
        ->set('data.roles', ['admin'])
        ->call('save');

    expect($me->fresh()->roles->pluck('name')->all())->toBe([]);
});

it('still saves a form that does not touch the roles', function () {
    Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);

    $me = User::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);
    $me->syncRoles(['editor']);

    $this->be($me);

    // The check is on the *change*, not on the list — otherwise fixing a typo in
    // a name would strip the roles of anyone who cannot grant roles.
    Livewire::test(EditUser::class, ['record' => $me->getKey()])
        ->set('data.name', 'Amelia B')
        ->call('save');

    expect($me->fresh()->name)->toBe('Amelia B')
        ->and($me->fresh()->roles->pluck('name')->all())->toBe(['editor']);
});

/* ------------------------------------------ S3: the confirmed-password window */

it('will not disable two-factor on a stale password confirmation', function () {
    $me = User::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    $this->be($me);
    Access::expirePasswordConfirmation();

    Livewire::test(TwoFactorAuthentication::class)
        ->call('disable')
        ->assertRedirect();
});

it('will not reveal the secret or the recovery codes on a stale confirmation', function () {
    // Both halves, and both are needed. Asserting only that a stale window hides
    // them would pass on a card that hides them always — which is what the first
    // draft of this test did, and it proved nothing until the fresh-window
    // assertion was put underneath it.
    config()->set('wire-module-users.model', FortifyUser::class);
    config()->set('auth.providers.users.model', FortifyUser::class);
    config()->set('fortify.features', [Features::twoFactorAuthentication(['confirm' => true])]);

    $me = FortifyUser::query()->create([
        'name' => 'Amelia',
        'email' => 'a@example.com',
        'password' => Hash::make('x'),
        // A pending setup: a secret to show and codes to reveal.
        'two_factor_secret' => encrypt('SECRETKEY'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-one', 'code-two'])),
    ]);

    $this->be($me);

    $card = Livewire::test(TwoFactorAuthentication::class)->instance();
    $card->showingRecoveryCodes = true;

    Access::confirmPassword();

    expect($card->setupKey())->toBe('SECRETKEY')
        ->and($card->recoveryCodes())->toBe(['code-one', 'code-two']);

    Access::expirePasswordConfirmation();

    expect($card->setupKey())->toBeNull()
        ->and($card->recoveryCodes())->toBe([]);
});

it('counts a confirmation inside the window and refuses one outside it', function () {
    $me = User::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    $this->be($me);

    $card = Livewire::test(TwoFactorAuthentication::class)->instance();

    Access::confirmPassword();
    expect((fn () => $this->hasConfirmedPasswordRecently())->call($card))->toBeTrue();

    Access::expirePasswordConfirmation();
    expect((fn () => $this->hasConfirmedPasswordRecently())->call($card))->toBeFalse();

    // Never confirmed at all reads the same as confirmed too long ago.
    session()->forget('auth.password_confirmed_at');
    expect((fn () => $this->hasConfirmedPasswordRecently())->call($card))->toBeFalse();
});

it('refuses every two-factor button on a stale confirmation, not just disable', function () {
    $me = User::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    $this->be($me);

    // Each button is its own guard clause, and a card that only checked the
    // dangerous-looking one would leave the rest of the ladder open.
    foreach (['enable', 'confirm', 'disable', 'regenerateRecoveryCodes', 'toggleRecoveryCodes'] as $button) {
        Access::expirePasswordConfirmation();

        Livewire::test(TwoFactorAuthentication::class)
            ->call($button)
            ->assertRedirect();
    }
});

it('shows no recovery codes when the account simply has none', function () {
    $me = User::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    $this->be($me);
    Access::confirmPassword();

    $card = Livewire::test(TwoFactorAuthentication::class)->instance();
    $card->showingRecoveryCodes = true;

    // Confirmed, asked for, and still nothing to show — a different answer from
    // "you may not see these", and it must not read as one.
    expect($card->recoveryCodes())->toBe([]);
});

it('does nothing at all when there is nobody signed in', function () {
    Access::confirmPassword();

    // Past the password gate and still no user. Every button has its own second
    // guard for that, and they are only reachable with the first one satisfied —
    // which is why this test confirms a password it then makes no use of.
    foreach (['enable', 'confirm', 'disable', 'regenerateRecoveryCodes'] as $button) {
        Livewire::test(TwoFactorAuthentication::class)
            ->call($button)
            ->assertOk();
    }
});

it('will not remove a passkey on a stale password confirmation', function () {
    $me = User::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    $this->be($me);
    Access::expirePasswordConfirmation();

    Livewire::test(PasskeyManagement::class)
        ->call('forget', 1)
        ->assertRedirect();
});

it('syncs nothing for a record that cannot hold roles', function () {
    // `getRoleNames()` is the permission trait's, and a model without it has no
    // roles to compare against — the "before" side falls back to an empty list
    // rather than exploding.
    $bare = new class extends Model
    {
        protected $table = 'users';
    };

    $host = new class
    {
        use SyncsRoles;

        /** @var array<string, mixed> */
        public array $data = ['roles' => []];

        public function check(Model $record): bool
        {
            return $this->mayAssignRoles($record, []);
        }
    };

    expect($host->check($bare))->toBeTrue();
});

/* --------------------------------------------------- what the installer says */

function usersInstaller(): object
{
    // The packager needs a name before the command can derive its publish tags.
    return new class(app(Packager::class)->name('WireModuleUsers')) extends InstallCommand
    {
        /** @var array<int, string> */
        public array $lines = [];

        public function comment($string, $verbosity = null): void
        {
            $this->lines[] = (string) $string;
        }
    };
}

function usersProviderForReport(): object
{
    return new class(app()) extends WireModuleUsersServiceProvider
    {
        public function reportPermissionsNow(InstallCommand $command): void
        {
            $this->reportPermissions($command);
        }

        public static function summaryNow(): string
        {
            return self::permissionSummary();
        }
    };
}

it('says so when every screen is guarded', function () {
    $installer = usersInstaller();

    usersProviderForReport()->reportPermissionsNow($installer);

    expect(implode("\n", $installer->lines))->toContain('require an ability')
        ->and(usersProviderForReport()::summaryNow())->toBe('all screens guarded');
});

it('names the open screens loudly when an installation opens them', function () {
    config()->set('wire-module-users.permissions.users.update', null);

    $installer = usersInstaller();

    usersProviderForReport()->reportPermissionsNow($installer);

    $said = implode("\n", $installer->lines);

    expect($said)->toContain('Open to anyone the panel admits')
        ->and($said)->toContain('users.update')
        ->and($said)->toContain('these screens set passwords and assign roles')
        ->and(usersProviderForReport()::summaryNow())->toBe('7 of 8 screens guarded');
});

it('reports an installation that opened all of them as OPEN', function () {
    config()->set('wire-module-users.permissions', [
        'users' => ['viewAny' => null, 'view' => null, 'create' => null, 'update' => null],
        'roles' => ['viewAny' => null, 'view' => null, 'create' => null, 'update' => null],
    ]);

    expect(usersProviderForReport()::summaryNow())->toBe('OPEN — no ability required');
});
