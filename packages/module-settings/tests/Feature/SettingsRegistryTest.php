<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Support\SettingsGroups;
use NyonCode\WireModuleSettings\Support\SettingsRegistry;

/*
 * A package contributing a settings tab.
 *
 * `wire-module-settings.groups` stayed the application's list — a panel's owner
 * says what their panel configures. What it could not express is the other half:
 * a package that ships a feature and the tab that configures it, which had to
 * end its README with "now add this class to your config" — the one instruction
 * every other surface in this framework had already stopped giving.
 */

class SrgAppGroup implements SettingsGroup
{
    public static function group(): string
    {
        return 'app';
    }

    public static function label(): string
    {
        return 'From the application';
    }

    public static function schema(): array
    {
        return [TextInput::make('one')];
    }
}

class SrgPackageGroup implements DescribesSettingsGroup, SettingsGroup
{
    public static function group(): string
    {
        return 'shipped';
    }

    public static function label(): string
    {
        return 'From a package';
    }

    public static function schema(): array
    {
        return [TextInput::make('two')];
    }

    public static function icon(): ?string
    {
        return 'outline:envelope';
    }

    public static function description(): ?string
    {
        return null;
    }

    public static function sort(): int
    {
        return 50;
    }
}

/** The same storage group as the package one, as an application might redeclare it. */
class SrgOverridingGroup implements SettingsGroup
{
    public static function group(): string
    {
        return 'shipped';
    }

    public static function label(): string
    {
        return 'The application version';
    }

    public static function schema(): array
    {
        return [TextInput::make('three')];
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

    config()->set('wire-module-settings.groups', []);
    config()->set('wire-module-settings.except', []);
});

it('shows a group a package registered, with nothing in config', function () {
    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    expect(array_keys(SettingsGroups::all()))->toBe(['shipped']);
});

it('keeps the application list and the contributed one in one screen', function () {
    config()->set('wire-module-settings.groups', [SrgAppGroup::class]);
    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    Livewire::test(SettingsPage::class)
        ->assertSee('data-group="app"', escape: false)
        ->assertSee('data-group="shipped"', escape: false);
});

it('puts the application ahead of a package among groups that name no order', function () {
    // Their panel, their order. A `sort()` still overrules it — this is only
    // what happens when nobody said anything.
    config()->set('wire-module-settings.groups', [SrgAppGroup::class]);
    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    expect(array_keys(SettingsGroups::all()))->toBe(['app', 'shipped']);
});

it('lets the application replace a group a package contributed', function () {
    // An application that declares `shipped` itself has answered the question,
    // and the package must not answer it again over the top.
    config()->set('wire-module-settings.groups', [SrgOverridingGroup::class]);
    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    expect(SettingsGroups::all())->toBe(['shipped' => SrgOverridingGroup::class]);
});

it('lets the application drop a contributed group outright', function () {
    // The reason a package may ship one at all: a package-shipped screen an
    // application cannot remove is what makes people stop installing them.
    SettingsRegistry::instance()->register(SrgPackageGroup::class);
    config()->set('wire-module-settings.except', ['shipped']);

    expect(SettingsGroups::all())->toBe([]);
});

it('registers a group once however many times a provider boots', function () {
    // A provider can boot twice — a package required by two others, a test that
    // boots the application again — and two copies of one group is a duplicate
    // tab whose second copy points at the same storage.
    SettingsRegistry::instance()->register(SrgPackageGroup::class);
    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    expect(SettingsRegistry::instance()->all())->toBe([SrgPackageGroup::class]);
});

it('ignores a contributed class that is not a settings group', function () {
    SettingsRegistry::instance()->register(stdClass::class);

    expect(SettingsRegistry::instance()->all())->toBe([]);
});

it('answers whether anything contributed a storage group', function () {
    expect(SettingsRegistry::instance()->has('shipped'))->toBeFalse();

    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    expect(SettingsRegistry::instance()->has('shipped'))->toBeTrue()
        ->and(SettingsRegistry::instance()->has('app'))->toBeFalse();
});

it('is the same registry whether it is reached statically or from the container', function () {
    // The ordering trap this exists for: a contributing package's provider may
    // run before this module's, so whichever gets there first has to be the one
    // instance the other finds. Resolving an unbound concrete class hands out a
    // fresh object every time, and the tab would then be missing on some
    // machines depending on a lockfile.
    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    expect(app(SettingsRegistry::class))->toBe(SettingsRegistry::instance())
        ->and(app(SettingsRegistry::class)->all())->toBe([SrgPackageGroup::class]);
});

it('reads a contributed group the same way it reads a declared one', function () {
    // Not a second kind of group: same contract, same defaults, same storage.
    SettingsRegistry::instance()->register(SrgPackageGroup::class);

    expect(SettingsGroups::icon(SrgPackageGroup::class))->toBe('outline:envelope')
        ->and(SettingsGroups::sort(SrgPackageGroup::class))->toBe(50)
        ->and(Livewire::test(SettingsPage::class, ['record' => 'shipped'])->get('group'))->toBe('shipped');
});
