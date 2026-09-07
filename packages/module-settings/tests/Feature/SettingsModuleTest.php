<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Support\Settings;

/*
 * Settings: storage and a screen from the module, and *what* is configurable
 * from the application. A package that shipped its own list of settings would be
 * guessing what an application needs.
 */

class SmBrandingSettings implements SettingsGroup
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
        return [
            TextInput::make('company_name'),
            Toggle::make('dark_default'),
        ];
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

    config()->set('wire-module-settings.groups', [SmBrandingSettings::class]);
});

it('registers itself as the settings module', function () {
    expect(app(PluginManager::class)->has('settings'))->toBeTrue();
});

it('keeps a value with the type it was given', function () {
    // A settings table that stringifies everything makes every reader cast by
    // hand, and they disagree.
    Settings::set('dark_default', true, 'branding');
    Settings::set('retries', 3, 'branding');
    Settings::set('recipients', ['a@example.com'], 'branding');

    expect(Settings::get('dark_default', null, 'branding'))->toBeTrue()
        ->and(Settings::get('retries', null, 'branding'))->toBe(3)
        ->and(Settings::get('recipients', null, 'branding'))->toBe(['a@example.com']);
});

it('answers the default for a key nobody has set', function () {
    expect(Settings::get('missing', 'fallback', 'branding'))->toBe('fallback');
});

it('answers the default when the table is not there at all', function () {
    // A settings call sits in application code that also runs during `migrate`
    // and in a fresh install; throwing there would make the module unsafe to
    // reference anywhere early.
    Schema::drop('wire_settings');
    Settings::forget('branding');

    expect(Settings::get('company_name', 'Acme', 'branding'))->toBe('Acme');
});

it('drops the cache when a value is written', function () {
    expect(Settings::get('company_name', null, 'branding'))->toBeNull();

    Settings::set('company_name', 'Acme', 'branding');

    expect(Settings::get('company_name', null, 'branding'))->toBe('Acme');
});

it('edits a declared group through the page', function () {
    Livewire::test(SettingsPage::class, ['group' => 'branding'])
        ->assertOk()
        ->assertSee('Branding')
        ->set('data.company_name', 'Acme')
        ->set('data.dark_default', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::all('branding'))->toBe(['company_name' => 'Acme', 'dark_default' => true]);
});

it('seeds the page with what is stored', function () {
    Settings::fill(['company_name' => 'Acme'], 'branding');

    expect(Livewire::test(SettingsPage::class, ['group' => 'branding'])->get('data.company_name'))->toBe('Acme');
});

it('says so when the application has declared nothing configurable', function () {
    config()->set('wire-module-settings.groups', []);

    Livewire::test(SettingsPage::class)
        ->assertOk()
        ->assertSee('data-testid="settings-empty"', escape: false);
});

it('ignores a listed class that is not a settings group', function () {
    config()->set('wire-module-settings.groups', [stdClass::class, SmBrandingSettings::class]);

    expect(array_keys(Livewire::test(SettingsPage::class)->instance()->groups()))->toBe(['branding']);
});
