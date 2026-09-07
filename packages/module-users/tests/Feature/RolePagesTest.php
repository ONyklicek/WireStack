<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleUsers\Pages\CreateRole;
use NyonCode\WireModuleUsers\Pages\EditRole;
use NyonCode\WireModuleUsers\Pages\ListRoles;
use NyonCode\WireModuleUsers\Pages\ViewRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Roles, where the application has them.
 *
 * Permissions are the same shape as a user's roles — a pivot, so written after
 * the record exists — and the guard is the field an application forgets, which
 * is why an empty one falls back rather than failing.
 */

beforeEach(function () {
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

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    Permission::create(['name' => 'invoices.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'invoices.*', 'guard_name' => 'web']);
    Role::create(['name' => 'editor', 'guard_name' => 'web']);
});

it('lists the roles', function () {
    Livewire::test(ListRoles::class)->assertOk()->assertSee('editor');
});

it('creates a role and gives it the permissions that were picked', function () {
    Livewire::test(CreateRole::class)
        ->set('data.name', 'auditor')
        ->set('data.permissions', ['invoices.view'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::where('name', 'auditor')->first();

    expect($role)->not->toBeNull()
        ->and($role->guard_name)->toBe('web')
        ->and($role->permissions->pluck('name')->all())->toBe(['invoices.view']);
});

it('falls back to the application guard when the field is left empty', function () {
    // The field an application forgets. Spatie refuses a role with no guard, so
    // the form answers the question instead of passing the refusal on.
    Livewire::test(CreateRole::class)
        ->set('data.name', 'support')
        ->set('data.guard_name', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::where('name', 'support')->first()?->guard_name)->toBe(config('auth.defaults.guard'));
});

it('edits permissions as a searchable checklist, not a multi-select', function () {
    // The two answer different questions. A multi-select shows what you have
    // *chosen* and hides the rest, which is right for the three roles on a user;
    // a permission list is the opposite — the question is always "what else is
    // there", over a space nobody has memorised.
    $html = Livewire::test(CreateRole::class)->assertOk()->html();

    expect($html)->toContain('data-testid="form-checklist-data.permissions-search"')
        ->toContain('data-testid="form-checklist-data.permissions-select-all"')
        ->toContain('data-testid="form-checklist-data.permissions-invoices.view"')
        // Every permission is on the page, which is the whole difference.
        ->toContain('data-testid="form-checklist-data.permissions-invoices.*"')
        // And what is granted stays visible above it: with a search typed or the
        // list scrolled, the ticks themselves are off screen.
        ->toContain('data-testid="form-checklist-data.permissions-selected"');
});

it('groups the permissions by the resource they are about', function () {
    // A flat alphabetical wall of two hundred names is the thing that made the
    // old field unusable; the segment before the first dot is the resource, and
    // grouping on it is the matrix people think in.
    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);

    $html = Livewire::test(CreateRole::class)->assertOk()->html();

    expect(substr_count($html, 'data-testid="form-checklist-data.permissions-group"'))->toBe(2)
        ->and($html)->toContain('invoices')->toContain('users');
});

it('stays flat where grouping would not help', function () {
    // One group is the same list with a heading over it. The fixture's two
    // permissions are both `invoices.`, so there is nothing to separate.
    $html = Livewire::test(CreateRole::class)->assertOk()->html();

    expect($html)->not->toContain('data-testid="form-checklist-data.permissions-group"');
});

it('seeds a role with the permissions it has, wildcards included', function () {
    Role::where('name', 'editor')->first()->syncPermissions(['invoices.*']);

    $page = Livewire::test(EditRole::class, ['record' => 1]);

    expect($page->get('data.permissions'))->toBe(['invoices.*']);

    $page->set('data.permissions', ['invoices.view'])->call('save')->assertHasNoErrors();

    expect(Role::first()->fresh()->permissions->pluck('name')->all())->toBe(['invoices.view']);
});

/** A role model with no permissions relation at all. */
class RpPlainRole extends Model
{
    protected $table = 'roles';

    protected $guarded = [];

    public $timestamps = false;
}

/** A page over that model, which is what an application swapping the model gets. */
class RpEditPlainRole extends EditRole
{
    protected function resolveRecord(): ?Model
    {
        return RpPlainRole::query()->find($this->record);
    }
}

/** And a page whose save is a command rather than a model write. */
class RpRoleWithCustomSave extends EditRole
{
    public function form(Form $form): Form
    {
        return parent::form($form)->using(static fn (array $data): string => 'handed to a command');
    }
}

it('seeds no permissions for a record that has no such relation', function () {
    // An application may point the module at a role model of its own. The screen
    // still renders; the permissions field is simply empty.
    expect(Livewire::test(RpEditPlainRole::class, ['record' => 1])->get('data.permissions'))->toBe([]);
});

it('writes no permissions when the save answered with something that is not a model', function () {
    Livewire::test(RpRoleWithCustomSave::class, ['record' => 1])
        ->set('data.permissions', ['invoices.view'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::first()->permissions)->toHaveCount(0);
});

it('shows one role, its wildcards apart from the permissions they cover', function () {
    // The reason the view page exists: a wildcard is a rule and a permission is
    // an entry, and one alphabetical row of chips hides the rule that makes half
    // of the row redundant.
    Role::where('name', 'editor')->first()->syncPermissions(['invoices.*', 'invoices.view']);

    $html = Livewire::test(ViewRole::class, ['record' => 1])->assertOk()->html();

    expect($html)->toContain('editor')
        ->and($html)->toContain('invoices.*')
        ->and($html)->toContain('invoices.view')
        // Both sections drawn, so the split is visible rather than implied.
        ->and($html)->toContain(__('wire-module-users::messages.wildcards'))
        ->and($html)->toContain(__('wire-module-users::messages.granted'));
});

it('says so rather than drawing an empty row when a role grants nothing', function () {
    $html = Livewire::test(ViewRole::class, ['record' => 1])->html();

    expect($html)->toContain(__('wire-module-users::messages.no_permissions'));
});

/** A view page over the model with no permissions relation at all. */
class RpViewPlainRole extends ViewRole
{
    protected function resolveRecord(): ?Model
    {
        return RpPlainRole::query()->find($this->record);
    }
}

it('renders a role model that has no permissions relation', function () {
    // An application may point the module at a role model of its own. The screen
    // still renders; every permission list is simply empty.
    Livewire::test(RpViewPlainRole::class, ['record' => 1])
        ->assertOk()
        ->assertSee(__('wire-module-users::messages.no_permissions'));
});

it('heads the page with the role, not with the word "role"', function () {
    // The title is also the last breadcrumb, so the resource singular put
    // "Roles / Role" over a heading that said "Role" a third time.
    $page = Livewire::test(ViewRole::class, ['record' => 1]);

    expect($page->instance()->getTitle())->toBe('editor');
});

it('falls back to the resource label for a record with no name', function () {
    Role::where('name', 'editor')->first()->update(['name' => '']);

    expect(Livewire::test(ViewRole::class, ['record' => 1])->instance()->getTitle())
        ->toBe(__('wire-module-users::messages.role'));
});

/** A page that names its own heading, the way an application overriding one does. */
class RpTitledViewRole extends ViewRole
{
    protected ?string $title = 'Who may do what';
}

it('leaves an explicitly named title alone', function () {
    expect(Livewire::test(RpTitledViewRole::class, ['record' => 1])->instance()->getTitle())
        ->toBe('Who may do what');
});
