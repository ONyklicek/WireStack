<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleUsers\Http\Middleware\SetCurrentTeam;
use NyonCode\WireModuleUsers\Livewire\DeleteAccount;
use NyonCode\WireModuleUsers\Livewire\TeamSwitcher;
use NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication;
use NyonCode\WireModuleUsers\Livewire\UpdatePassword;
use NyonCode\WireModuleUsers\Pages\EditProfile;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Avatars;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use NyonCode\WireModuleUsers\Support\TwoFactor;
use NyonCode\WireModuleUsers\Tests\Fixtures\AvatarUser;
use NyonCode\WireModuleUsers\Tests\Fixtures\Team;
use NyonCode\WireModuleUsers\Tests\Fixtures\TeamUser;
use NyonCode\WireModuleUsers\WireModuleUsersServiceProvider;
use NyonCode\WireTable\Table;

/*
 * What the optional surfaces do when the application is not the happy path.
 *
 * Every `auto` in this module is a question asked about somebody else's
 * installation — a column that may not be there, a disk that may not build a
 * URL, a relation that may not be a relation. The answers below are the ones
 * that keep a profile page drawing rather than throwing, and they are the ones
 * nobody exercises by accident.
 */

beforeEach(function () {
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

afterEach(fn () => Avatars::flush());

// ─── Avatars, where the application is not what was expected ────────────────

it('survives a configured model that is not a model at all', function () {
    // `new $model` succeeds and the first thing asked of it does not. A page that
    // was trying to draw a form should not answer with a stack trace.
    config()->set('wire-module-users.model', stdClass::class);

    expect(Avatars::available())->toBeFalse();
});

it('draws no picture for something that is not a record', function () {
    expect(Avatars::urlFor(null))->toBeNull()
        ->and(Avatars::urlFor('a string'))->toBeNull();
});

it('draws no picture when the module is switched off, whatever is stored', function () {
    $me = AvatarUser::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
        'avatar_path' => 'avatars/amelia.png',
    ]);

    config()->set('wire-module-users.avatar.enabled', false);

    expect(Avatars::urlFor($me))->toBeNull();
});

it('falls back to initials when the disk cannot build a URL', function () {
    // A disk with no URL generator is a misconfiguration this page survives:
    // initials are a worse avatar, not a broken one.
    config()->set('wire-module-users.avatar.disk', 'a-disk-nobody-configured');

    $me = AvatarUser::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
        'avatar_path' => 'avatars/amelia.png',
    ]);

    expect(Avatars::urlFor($me))->toBeNull();
});

it('puts a face in the users list where there is a column for one', function () {
    Storage::fake('public');

    $columns = (new UserResource)->table(app(Table::class))->getColumns();

    $names = array_map(static fn (object $column): string => $column->getName(), $columns);

    expect($names)->toContain('avatar_path')
        ->and($names[0])->toBe('avatar_path');
});

// ─── The profile's card list, each switch both ways ─────────────────────────

it('draws every card when the installation has every half', function () {
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);
    config()->set('wire-module-users.profile.delete_account', true);

    $this->be(AvatarUser::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]));

    expect(Livewire::test(EditProfile::class)->instance()->cards())
        ->toBe([UpdatePassword::class, TwoFactorAuthentication::class, DeleteAccount::class]);
});

it('draws none of them when the installation asked for none', function () {
    config()->set('wire-module-users.profile.password', false);
    config()->set('wire-module-users.profile.two_factor', false);
    config()->set('wire-module-users.profile.delete_account', false);

    $this->be(AvatarUser::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]));

    expect(Livewire::test(EditProfile::class)->instance()->cards())->toBe([]);
});

// ─── The cards, with nobody signed in ───────────────────────────────────────

it('changes no password for nobody', function () {
    // The card can be rendered on a page nobody is signed in to. That is not an
    // error worth an exception on a profile page.
    Livewire::test(UpdatePassword::class)
        ->set('data.current_password', 'anything')
        ->set('data.password', 'a-much-longer-new-one')
        ->set('data.password_confirmation', 'a-much-longer-new-one')
        ->call('save')
        ->assertOk();

    expect(Auth::check())->toBeFalse();
});

it('deletes no account for nobody', function () {
    expect(Livewire::test(DeleteAccount::class)->call('delete')->instance())
        ->toBeInstanceOf(DeleteAccount::class);
});

it('names the hook target each card can be intercepted through', function () {
    // A module that ships a surface and no name for it is a surface an
    // application cannot adjust — see ADR 0030.
    expect((new UpdatePassword)->hookKey())->toBe('users.password')
        ->and((new DeleteAccount)->hookKey())->toBe('users.delete-account')
        ->and((new TwoFactorAuthentication)->hookKey())->toBe('users.two-factor')
        ->and((new TeamSwitcher)->hookKey())->toBe('users.team-switcher');
});

// ─── Two-factor, where Fortify is installed but the model is not ready ──────

it('answers null for a QR code and a key when there is nothing pending', function () {
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    $this->be(AvatarUser::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]));

    // This user model has neither Fortify's trait nor its columns — an
    // application that turned the feature on and has not migrated yet.
    $card = Livewire::test(TwoFactorAuthentication::class)->instance();

    expect($card->qrCodeSvg())->toBeNull()
        ->and($card->setupKey())->toBeNull()
        ->and($card->recoveryCodes())->toBe([]);
});

it('reads confirmation as required when there is no Fortify feature to ask', function () {
    config()->set('fortify.features', []);

    expect(TwoFactor::confirmationRequired())->toBeTrue();
});

it('treats a user object with no attributes as having no two-factor', function () {
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    // A custom guard's user object is an Authenticatable without getAttribute().
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    expect(TwoFactor::pending($user))->toBeFalse()
        ->and(TwoFactor::confirmed($user))->toBeFalse();
});

// ─── Teams, where the application is not what was expected ─────────────────

it('offers no teams to something that is not a record', function () {
    config()->set('permission.teams', true);
    config()->set('wire-module-users.teams.model', Team::class);

    expect(Teams::optionsFor('a string'))->toBe([]);
});

it('offers no teams when the user model has no such relation', function () {
    config()->set('permission.teams', true);
    config()->set('wire-module-users.teams.model', Team::class);

    // `AvatarUser` has no `teams()` — an application that turned the setting on
    // and has not written the relation yet.
    $me = AvatarUser::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    expect(Teams::optionsFor($me))->toBe([]);
});

it('offers no teams when the relation is there and the table is not', function () {
    config()->set('permission.teams', true);
    config()->set('wire-module-users.teams.model', Team::class);
    config()->set('wire-module-users.model', TeamUser::class);

    $me = TeamUser::query()->create([
        'name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x'),
    ]);

    // No `teams` table in this test's schema: a query that cannot run means this
    // installation has no teams to offer, not that a page should fail.
    expect(Teams::optionsFor($me))->toBe([]);
});

it('scopes the request through a middleware that is inert without teams', function () {
    config()->set('permission.teams', false);

    $response = (new SetCurrentTeam)->handle(
        Request::create('/'),
        fn (Request $request) => response('ok'),
    );

    expect($response->getContent())->toBe('ok');
});

// ─── What the provider registers, and what it reports ───────────────────────

/**
 * The provider's own two protected halves, reached the way a test reaches a
 * protected method without making it public for the sake of being reached.
 */
function usersProvider(): WireModuleUsersServiceProvider
{
    return new class(app()) extends WireModuleUsersServiceProvider
    {
        public function bootTeamsNow(): void
        {
            $this->bootTeams();
        }

        public function bootUserMenuNow(): void
        {
            $this->bootUserMenu();
        }

        /** @return array<int, string> */
        public function features(): array
        {
            return $this->optionalFeatures();
        }
    };
}

it('registers the middleware and the switcher only where teams are on', function () {
    config()->set('permission.teams', true);
    config()->set('wire-module-users.teams.model', Team::class);

    usersProvider()->bootTeamsNow();

    // Onto the group rather than behind an alias a route opts into: a page that
    // forgot the alias would authorize against the wrong team while looking
    // perfectly correct.
    expect(app(PageChrome::class)->has('wire-module-users::team-switcher', PageChrome::TOPBAR))->toBeTrue()
        ->and(app(Kernel::class)->getMiddlewareGroups()['web'] ?? [])->toContain(SetCurrentTeam::class);
});

it('registers neither where teams are off', function () {
    config()->set('permission.teams', false);

    usersProvider()->bootTeamsNow();

    expect(app(PageChrome::class)->has('wire-module-users::team-switcher', PageChrome::TOPBAR))->toBeFalse();
});

it('groups permissions by resource, and only where that separates them', function () {
    // The rule, without a screen around it. Two branches matter: an application
    // whose permissions are not dotted (`view invoices`, Spatie's other common
    // convention) would get one group per permission, which has grouped nothing.
    $flat = ['invoices.view' => 'invoices.view', 'invoices.create' => 'invoices.create'];
    $mixed = $flat + ['users.view' => 'users.view', 'users.create' => 'users.create'];
    $undotted = ['view invoices' => 'view invoices', 'edit users' => 'edit users'];

    expect(Roles::groupPermissions($mixed))->toBe([
        'invoices' => ['invoices.view' => 'invoices.view', 'invoices.create' => 'invoices.create'],
        'users' => ['users.view' => 'users.view', 'users.create' => 'users.create'],
    ])
        // One group is the same list with a heading over it.
        ->and(Roles::groupPermissions($flat))->toBe([])
        // As many groups as permissions is worse than none — which is what an
        // undotted convention produces, and also what three permissions across
        // three resources produce, so both decline.
        ->and(Roles::groupPermissions($undotted))->toBe([])
        ->and(Roles::groupPermissions([]))->toBe([]);
});

it('gathers the permissions with no resource in them under one heading', function () {
    // Dropped from the form, a permission is one nobody can grant — so an
    // undotted name beside dotted ones is kept, under a heading of its own.
    // Five permissions across three groups, because three across three is not a
    // grouping — it is three headings over three checkboxes, and the rule below
    // declines it on purpose.
    $groups = Roles::groupPermissions([
        'invoices.view' => 'invoices.view',
        'invoices.create' => 'invoices.create',
        'users.view' => 'users.view',
        'users.create' => 'users.create',
        'impersonate' => 'impersonate',
    ]);

    expect($groups)->toHaveKey(__('wire-module-users::messages.other_permissions'))
        ->and($groups[__('wire-module-users::messages.other_permissions')])
        ->toBe(['impersonate' => 'impersonate']);
});

it('puts the link to the profile page in the user menu', function () {
    // The module owns the page, so the module contributes the link. It used to
    // be a layout slot every application wrote by hand — reaching into this
    // package's translations from a file this package cannot see.
    app()->instance(PageChrome::class, new PageChrome);

    usersProvider()->bootUserMenuNow();

    expect(app(PageChrome::class)->views(PageChrome::USER_MENU))
        ->toBe(['wire-module-users::profile-menu-item']);
});

it('leaves the menu alone where the application puts the link somewhere else', function () {
    app()->instance(PageChrome::class, new PageChrome);
    config()->set('wire-module-users.profile.menu_item', false);

    usersProvider()->bootUserMenuNow();

    expect(app(PageChrome::class)->views(PageChrome::USER_MENU))->toBe([]);
});

it('sorts the profile link above what the auth module contributes', function () {
    // Provider order is composer's discovery order, and neither package can see
    // the other. "Sign out" first reads as a bug.
    app()->instance(PageChrome::class, new PageChrome);

    app(PageChrome::class)->add('wire-module-auth::user-menu', PageChrome::USER_MENU, 100);
    usersProvider()->bootUserMenuNow();

    expect(app(PageChrome::class)->views(PageChrome::USER_MENU))->toBe([
        'wire-module-users::profile-menu-item',
        'wire-module-auth::user-menu',
    ]);
});

it('reports each optional half as on when the installation has it', function () {
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);
    config()->set('permission.teams', true);
    config()->set('wire-module-users.teams.model', Team::class);

    $lines = implode("\n", usersProvider()->features());

    expect($lines)->toContain('Avatars: the `avatar_path` column is there')
        ->and($lines)->toContain('Two-factor: Fortify is installed')
        ->and($lines)->toContain('Teams: permission.teams is on');
});

it('says how to get each half it does not have', function () {
    config()->set('wire-module-users.avatar.enabled', false);
    config()->set('fortify.features', []);
    config()->set('permission.teams', false);

    $lines = implode("\n", usersProvider()->features());

    expect($lines)->toContain('add a nullable `avatar_path` string column')
        ->and($lines)->toContain('install laravel/fortify')
        ->and($lines)->toContain('set permission.teams to true');
});
