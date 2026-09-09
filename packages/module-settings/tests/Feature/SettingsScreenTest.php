<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Support\Settings;

/*
 * What the screen actually renders. The page used to be a bare `<div>` with a
 * row of links in it — no heading, no trail back, nothing saying what a group
 * was for — which is the one thing every other page in this framework has.
 */

class SsPlain implements SettingsGroup
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

class SsDescribed implements DescribesSettingsGroup, SettingsGroup
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
        return [TextInput::make('two')];
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
        return 10;
    }
}

class SsGuarded implements GuardsSettingsGroup, SettingsGroup
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
        return [TextInput::make('three')];
    }

    public static function permission(): ?string
    {
        return 'settings.guarded';
    }
}

class SsSectioned implements SettingsGroup
{
    public static function group(): string
    {
        return 'sectioned';
    }

    public static function label(): string
    {
        return 'Sectioned';
    }

    public static function schema(): array
    {
        return [
            Section::make('one')
                ->label('One')
                ->schema([TextInput::make('inside')]),
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
});

it('is titled by the group that is open', function () {
    config()->set('wire-module-settings.groups', [SsPlain::class, SsDescribed::class]);

    $page = Livewire::test(SettingsPage::class, ['record' => 'plain']);

    expect($page->instance()->getTitle())->toBe('Plain');
});

it('falls back to the screen name when no group is open', function () {
    config()->set('wire-module-settings.groups', []);

    expect(Livewire::test(SettingsPage::class)->instance()->getTitle())->not->toBe('');
});

it('says where it sits, ending with the group', function () {
    config()->set('wire-module-settings.groups', [SsDescribed::class]);

    $crumbs = Livewire::test(SettingsPage::class)->instance()->breadcrumbs();

    expect($crumbs)->toHaveCount(2)
        ->and($crumbs[1]->getLabel())->toBe('Described');
});

it('renders the description a group declares', function () {
    config()->set('wire-module-settings.groups', [SsDescribed::class]);

    Livewire::test(SettingsPage::class)->assertSee('What this group is for.');
});

it('draws no switcher for a single group', function () {
    // One group is not a choice, so it is not drawn as one.
    config()->set('wire-module-settings.groups', [SsPlain::class]);

    Livewire::test(SettingsPage::class)
        ->assertOk()
        ->assertDontSee('data-testid="settings-groups"', escape: false);
});

it('draws the switcher once there is something to switch between', function () {
    config()->set('wire-module-settings.groups', [SsPlain::class, SsDescribed::class]);

    Livewire::test(SettingsPage::class)
        ->assertSee('data-testid="settings-groups"', escape: false)
        ->assertSee('data-group="plain"', escape: false)
        ->assertSee('data-group="described"', escape: false);
});

it('leaves a group this user may not see out of the switcher it renders', function () {
    config()->set('wire-module-settings.groups', [SsPlain::class, SsGuarded::class]);

    Livewire::test(SettingsPage::class)
        ->assertOk()
        ->assertDontSee('data-group="guarded"', escape: false);
});

it('names the message the save produces, rather than sending a second one', function () {
    // Through the form's own successMessage, which is what the profile page
    // does: an override of save() that sent its own toast would be two of them
    // for one save.
    config()->set('wire-module-settings.groups', [SsPlain::class]);

    $page = Livewire::test(SettingsPage::class)
        ->set('data.one', 'value')
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::get('one', null, 'plain'))->toBe('value')
        ->and(json_encode($page->effects['dispatches'] ?? []))
        ->toContain(__('wire-module-settings::messages.saved'));
});

it('gives a flat group its own card', function () {
    // Every resource form gets its surface from the Sections the resource
    // declared. A settings group is not obliged to declare any, and rendered
    // bare its inputs sit directly on the page background — the one screen in a
    // panel that does.
    config()->set('wire-module-settings.groups', [SsPlain::class]);

    Livewire::test(SettingsPage::class)
        ->assertSee('data-testid="settings-surface"', escape: false)
        ->assertOk();

    expect(Livewire::test(SettingsPage::class)->instance()->needsSurface())->toBeTrue();
});

it('leaves a group that brought its own layout alone', function () {
    // A card around a card is a border inside a border; the group already said
    // where its own edges are.
    config()->set('wire-module-settings.groups', [SsSectioned::class]);

    Livewire::test(SettingsPage::class)
        ->assertOk()
        ->assertDontSee('data-testid="settings-surface"', escape: false);

    expect(Livewire::test(SettingsPage::class)->instance()->needsSurface())->toBeFalse();
});
