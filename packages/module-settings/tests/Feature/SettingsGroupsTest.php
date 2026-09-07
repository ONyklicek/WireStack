<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Support\Settings;
use NyonCode\WireModuleSettings\Support\SettingsGroups;

/*
 * The three optional contracts a group may implement, and the one owner that
 * knows they exist. A caller asks `SettingsGroups::icon($class)` and never
 * writes an `is_a()` of its own — which is the whole reason the registry is a
 * class rather than four lines repeated in the page, the view and Settings.
 */

class SgPlain implements SettingsGroup
{
    public static function group(): string
    {
        return 'plain';
    }

    public static function label(): string
    {
        return 'Plain';
    }

    public static function schema(): array
    {
        return [TextInput::make('one')];
    }
}

class SgSecond implements SettingsGroup
{
    public static function group(): string
    {
        return 'second';
    }

    public static function label(): string
    {
        return 'Second';
    }

    public static function schema(): array
    {
        return [TextInput::make('two')];
    }
}

class SgDescribed implements DescribesSettingsGroup, SettingsGroup
{
    public static function group(): string
    {
        return 'described';
    }

    public static function label(): string
    {
        return 'Described';
    }

    public static function schema(): array
    {
        return [TextInput::make('three')];
    }

    public static function icon(): ?string
    {
        return 'outline:envelope';
    }

    public static function description(): ?string
    {
        return 'What this group is for.';
    }

    public static function sort(): int
    {
        return -10;
    }
}

class SgDefaulted implements ProvidesSettingsDefaults, SettingsGroup
{
    public static function group(): string
    {
        return 'defaulted';
    }

    public static function label(): string
    {
        return 'Defaulted';
    }

    public static function schema(): array
    {
        return [TextInput::make('from_address')];
    }

    public static function defaults(): array
    {
        return ['from_address' => 'noreply@example.com', 'queue' => true];
    }
}

class SgGuarded implements GuardsSettingsGroup, SettingsGroup
{
    public static function group(): string
    {
        return 'guarded';
    }

    public static function label(): string
    {
        return 'Guarded';
    }

    public static function schema(): array
    {
        return [TextInput::make('four')];
    }

    public static function permission(): ?string
    {
        return 'settings.guarded';
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
});

it('answers for a group that declares none of the optional contracts', function () {
    // The point of them being optional: a group with a heading and a schema is
    // still a whole group, and nothing asking about icons has to know that.
    expect(SettingsGroups::icon(SgPlain::class))->toBeNull()
        ->and(SettingsGroups::description(SgPlain::class))->toBeNull()
        ->and(SettingsGroups::sort(SgPlain::class))->toBe(0)
        ->and(SettingsGroups::defaults(SgPlain::class))->toBe([])
        ->and(SettingsGroups::permission(SgPlain::class))->toBeNull();
});

it('reads what a group does declare', function () {
    expect(SettingsGroups::icon(SgDescribed::class))->toBe('outline:envelope')
        ->and(SettingsGroups::description(SgDescribed::class))->toBe('What this group is for.')
        ->and(SettingsGroups::sort(SgDescribed::class))->toBe(-10);
});

it('puts a group that declares an order where it asked to be', function () {
    config()->set('wire-module-settings.groups', [SgPlain::class, SgDescribed::class]);

    expect(array_keys(SettingsGroups::all()))->toBe(['described', 'plain']);
});

it('keeps the declared order for the groups that name no order', function () {
    // Stable sort, and that is what makes sort() optional: one group moving must
    // not shuffle the ones that said nothing.
    config()->set('wire-module-settings.groups', [SgSecond::class, SgPlain::class, SgDescribed::class]);

    expect(array_keys(SettingsGroups::all()))->toBe(['described', 'second', 'plain']);
});

it('answers a declared default for a key nobody has stored', function () {
    config()->set('wire-module-settings.groups', [SgDefaulted::class]);

    expect(Settings::get('from_address', null, 'defaulted'))->toBe('noreply@example.com')
        ->and(Settings::get('queue', null, 'defaulted'))->toBeTrue();
});

it('lets a stored value win over the declared default', function () {
    config()->set('wire-module-settings.groups', [SgDefaulted::class]);

    Settings::set('from_address', 'hello@acme.test', 'defaulted');

    expect(Settings::get('from_address', null, 'defaulted'))->toBe('hello@acme.test');
});

it('separates a declared default from something actually stored', function () {
    config()->set('wire-module-settings.groups', [SgDefaulted::class]);

    // `get()` answers either; `has()` is the question "did anybody set this",
    // which is what a migration or an audit of the table needs.
    expect(Settings::get('queue', null, 'defaulted'))->toBeTrue()
        ->and(Settings::has('queue', 'defaulted'))->toBeFalse();
});

it('falls back to the caller default only for a key the group never declared', function () {
    config()->set('wire-module-settings.groups', [SgDefaulted::class]);

    expect(Settings::get('undeclared', 'caller', 'defaulted'))->toBe('caller');
});

it('shows a declared default in the form the first time the screen is opened', function () {
    config()->set('wire-module-settings.groups', [SgDefaulted::class]);

    expect(Livewire::test(SettingsPage::class)->get('data.from_address'))->toBe('noreply@example.com');
});

it('keeps a group its user may not see out of the switcher', function () {
    config()->set('wire-module-settings.groups', [SgPlain::class, SgGuarded::class]);

    expect(array_keys(SettingsGroups::all()))->toBe(['plain', 'guarded'])
        ->and(array_keys(SettingsGroups::visible()))->toBe(['plain']);
});

it('lets a group through once its ability is granted', function () {
    config()->set('wire-module-settings.groups', [SgGuarded::class]);

    Gate::define('settings.guarded', fn (): bool => true);
    $this->actingAs(new User);

    expect(array_keys(SettingsGroups::visible()))->toBe(['guarded']);
});

it('refuses to open a group this user may not see', function () {
    config()->set('wire-module-settings.groups', [SgPlain::class, SgGuarded::class]);

    Livewire::test(SettingsPage::class, ['record' => 'guarded'])->assertForbidden();
});

it('refuses to save a group this user may not see, even from a tampered snapshot', function () {
    // The group is a public property, so it rides in the snapshot and comes back
    // from the browser. A user who may open one group must not be able to save
    // another by editing the value that travels — which is why the check is at
    // the write and not only at the mount that put the value there.
    config()->set('wire-module-settings.groups', [SgPlain::class, SgGuarded::class]);

    Livewire::test(SettingsPage::class)
        ->set('group', 'guarded')
        ->call('save')
        ->assertForbidden();

    expect(Settings::has('four', 'guarded'))->toBeFalse();
});

it('denies a group when there is no guard to ask at all', function () {
    // Fail closed, the same way HasAuthorization does: a context where the guard
    // cannot be resolved — console, a queued job, a misconfigured guard —
    // authorizes nothing, and denying is the only safe answer. Rethrowing would
    // turn a menu render into a crash.
    config()->set('wire-module-settings.groups', [SgGuarded::class]);

    Gate::shouldReceive('allows')->andThrow(new RuntimeException('no guard'));

    expect(SettingsGroups::authorized(SgGuarded::class))->toBeFalse()
        ->and(SettingsGroups::visible())->toBe([]);
});

it('refuses the screen outright when nothing on it is this user\'s', function () {
    // Not the empty state: that one says "declare a SettingsGroup class and list
    // it in config", which is an instruction for the developer and a lie to
    // everybody else.
    config()->set('wire-module-settings.groups', [SgGuarded::class]);

    Livewire::test(SettingsPage::class)->assertForbidden();
});

it('still shows the empty state when the application has declared nothing', function () {
    config()->set('wire-module-settings.groups', []);

    Livewire::test(SettingsPage::class)
        ->assertOk()
        ->assertSee('data-testid="settings-empty"', escape: false);
});
