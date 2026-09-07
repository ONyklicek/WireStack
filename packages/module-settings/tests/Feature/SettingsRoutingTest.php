<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Resources\SettingsResource;

/*
 * A group is a URL.
 *
 * That sentence is why the switcher is links rather than tabs, and it was not
 * true: the route is `settings/{record}` — the shape every resource page in this
 * framework gets — so the group arrives under the name `record`, and a `mount()`
 * that took only `$group` matched neither a public property nor a mount
 * parameter. Livewire put the value in the component's HTML attributes and every
 * bookmarked group URL rendered the *first* group instead, looking for all the
 * world like it had worked.
 */

class SrMailSettings implements SettingsGroup
{
    public static function group(): string
    {
        return 'mail';
    }

    public static function label(): string
    {
        return 'Mail';
    }

    public static function schema(): array
    {
        return [TextInput::make('from_address')];
    }
}

class SrBrandingSettings implements SettingsGroup
{
    public static function group(): string
    {
        return 'branding';
    }

    public static function label(): string
    {
        return 'Branding';
    }

    public static function schema(): array
    {
        return [TextInput::make('company_name')];
    }
}

beforeEach(function () {
    Schema::create('wire_settings', function (Blueprint $table) {
        $table->id();
        $table->string('group')->default('general');
        $table->string('key');
        $table->json('value')->nullable();
        $table->timestamps();
        $table->unique(['group', 'key']);
    });

    // Branding first, so "opened the group the URL named" cannot pass by
    // accidentally falling back to the first one.
    config()->set('wire-module-settings.groups', [SrBrandingSettings::class, SrMailSettings::class]);
});

it('opens the group the URL names, not the first one', function () {
    $page = Livewire::test(SettingsPage::class, ['record' => 'mail']);

    expect($page->get('group'))->toBe('mail');
    $page->assertSee('Mail');
});

it('still opens a group named directly, for a page mounted by hand', function () {
    expect(Livewire::test(SettingsPage::class, ['group' => 'mail'])->get('group'))->toBe('mail');
});

it('opens the first group when the URL names none', function () {
    expect(Livewire::test(SettingsPage::class)->get('group'))->toBe('branding');
});

it('is a 404 for a group this application does not declare', function () {
    // Not an empty form: empty reads as "nothing to configure here" rather than
    // as "this group is gone", and a bookmark to a removed group is the second.
    Livewire::test(SettingsPage::class, ['record' => 'removed'])->assertNotFound();
});

it('ignores a route parameter that is not a group name at all', function () {
    // The parameter arrives as whatever was in the URL. An array from a crafted
    // query is "no group" rather than a type error three lines later.
    expect(Livewire::test(SettingsPage::class, ['record' => ['array']])->get('group'))->toBe('branding');
});

/**
 * The middleware on one of the module's registered routes.
 *
 * By name, and the lookup refreshed first: a route registered inside a test is
 * not in the name index until something asks for it to be, and `getByName()`
 * answers null rather than saying so.
 *
 * @return array<int, string>
 */
function srMiddleware(string $name): array
{
    $routes = Route::getRoutes();
    $routes->refreshNameLookups();

    return $routes->getByName($name)?->gatherMiddleware() ?? [];
}

it('routes both pages without a permission when none is configured', function () {
    Route::middleware('web')->group(fn () => Route::wireResource(SettingsResource::class));

    expect(srMiddleware('wire.settings.view'))->not->toContain('can:settings.manage')
        ->and(SettingsResource::urlForGroup('mail'))->toContain('settings/mail');
});

it('puts the configured ability on both routes', function () {
    config()->set('wire-module-settings.permission', 'settings.manage');

    Route::middleware('web')->group(fn () => Route::wireResource(SettingsResource::class));

    expect(srMiddleware('wire.settings.index'))->toContain('can:settings.manage')
        ->and(srMiddleware('wire.settings.view'))->toContain('can:settings.manage');
});

it('hides the menu entry from someone who fails that ability', function () {
    // A menu entry whose route answers 403 is worse than no entry: it is the
    // panel telling somebody a screen exists for them and then telling them it
    // does not.
    config()->set('wire-module-settings.permission', 'settings.manage');

    expect(SettingsResource::navigation()->isVisible())->toBeFalse();

    Gate::define('settings.manage', fn (): bool => true);
    $this->actingAs(new User);

    expect(SettingsResource::navigation()->isVisible())->toBeTrue();
});

it('keeps the entry for an application that has declared nothing yet', function () {
    // That screen carries the instructions for declaring a group. Hiding it
    // would mean the module answers a fresh installation by removing the only
    // page that explains itself.
    config()->set('wire-module-settings.groups', []);

    expect(SettingsResource::navigation()->isVisible())->toBeTrue();
});

it('hides the menu entry when there is no guard to ask at all', function () {
    config()->set('wire-module-settings.permission', 'settings.manage');

    Gate::shouldReceive('allows')->andThrow(new RuntimeException('no guard'));

    expect(SettingsResource::navigation()->isVisible())->toBeFalse();
});
