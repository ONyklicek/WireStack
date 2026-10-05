<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleSettings\Contracts\ConfirmsSettingsSave;
use NyonCode\WireModuleSettings\Contracts\ExtendsSettingsScreen;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\RendersSettingsScreen;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Contracts\SpansSettingsGroups;
use NyonCode\WireModuleSettings\Contracts\TransformsSettings;
use NyonCode\WireModuleSettings\Contracts\ValidatesSettings;
use NyonCode\WireModuleSettings\Events\SettingsSaved;
use NyonCode\WireModuleSettings\Exceptions\SettingsScreenException;
use NyonCode\WireModuleSettings\Models\Setting;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\SettingsModule;
use NyonCode\WireModuleSettings\Support\Settings;
use NyonCode\WireModuleSettings\Support\SettingsGroupValues;

/*
 * What a group can say about its own screen beyond a schema — so a settings
 * section an application already has can be built on the module instead of
 * beside it: values shaped on the way in and out, a rule across fields, one
 * screen over several storage groups, something drawn from the live state, a
 * screen of its own, and its own word for "saved".
 */

class GcShift implements SettingsGroup, TransformsSettings
{
    public static function group(): string
    {
        return 'shift';
    }

    public static function label(): string
    {
        return 'Shift';
    }

    public static function schema(): array
    {
        return [TextInput::make('start')];
    }

    public static function fromStorage(array $values): array
    {
        return ['start' => substr((string) ($values['start'] ?? ''), 0, 5)];
    }

    public static function toStorage(array $data): array
    {
        return ['start' => $data['start'].':00'];
    }
}

class GcNumbering implements ConfirmsSettingsSave, ExtendsSettingsScreen, SettingsGroup, ValidatesSettings
{
    public static function group(): string
    {
        return 'numbering';
    }

    public static function label(): string
    {
        return 'Numbering';
    }

    public static function schema(): array
    {
        return [TextInput::make('format'), TextInput::make('start'), TextInput::make('year')];
    }

    public static function validateSettings(array $data): array
    {
        return blank($data['start'] ?? null) === blank($data['year'] ?? null)
            ? []
            : ['start' => 'Number and year go together.'];
    }

    public static function screenExtension(array $data): ?Htmlable
    {
        return blank($data['format'] ?? null) ? null : new HtmlString('Preview: '.e($data['format']).'-001');
    }

    public static function savedMessage(): string
    {
        return 'Numbering saved.';
    }
}

class GcReports implements ProvidesSettingsDefaults, SettingsGroup, SpansSettingsGroups
{
    public static function group(): string
    {
        return 'reports';
    }

    public static function label(): string
    {
        return 'Reports';
    }

    public static function schema(): array
    {
        return [TextInput::make('format'), TextInput::make('interval'), TextInput::make('grace')];
    }

    public static function storage(): array
    {
        // Its own name listed too: ignored, the keys are there already.
        return ['timers' => ['interval', 'grace'], 'reports' => ['format']];
    }

    public static function defaults(): array
    {
        return ['format' => 'decimal', 'interval' => 15, 'grace' => 5];
    }
}

class GcScreenGroup implements RendersSettingsScreen, SettingsGroup
{
    public static function group(): string
    {
        return 'calculator';
    }

    public static function label(): string
    {
        return 'Calculator';
    }

    public static function schema(): array
    {
        return [];
    }

    public static function component(): string
    {
        return GcCalculator::class;
    }
}

class GcCalculator extends Component
{
    public string $group = '';

    public function render(): string
    {
        return '<div>own screen for {{ $group }}</div>';
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

// ── Values on the way in and out ───────────────────────────────────

it('shapes the stored value for the form and the form value for storage', function () {
    config()->set('wire-module-settings.groups', [GcShift::class]);
    Settings::set('start', '07:30:00', 'shift');

    $page = Livewire::test(SettingsPage::class)->assertSet('data.start', '07:30');

    $page->set('data.start', '06:15')->call('save')->assertHasNoErrors();

    expect(Settings::get('start', null, 'shift'))->toBe('06:15:00');
});

it('leaves a group without the contracts exactly as it was', function () {
    expect(SettingsGroupValues::problems(GcShift::class, ['start' => 'x']))->toBe([])
        ->and(SettingsGroupValues::extension(GcShift::class, []))->toBeNull()
        ->and(SettingsGroupValues::component(GcShift::class))->toBeNull()
        ->and(SettingsGroupValues::savedMessage(GcShift::class))->toBeNull();
});

// ── A rule across fields ───────────────────────────────────────────

it('stops the save at a rule across fields, with the message under the field', function (array $input, bool $saves) {
    config()->set('wire-module-settings.groups', [GcNumbering::class]);

    $page = Livewire::test(SettingsPage::class)->set('data', $input)->call('save');

    $saves
        ? $page->assertHasNoErrors()
        : $page->assertHasErrors(['data.start' => 'Number and year go together.']);

    expect(Settings::has('format', 'numbering'))->toBe($saves);
})->with([
    'number without its year' => [['format' => 'N', 'start' => '5', 'year' => ''], false],
    'both' => [['format' => 'N', 'start' => '5', 'year' => '2026'], true],
    'neither' => [['format' => 'N', 'start' => '', 'year' => ''], true],
]);

it('confirms a save in the group\'s own words', function () {
    config()->set('wire-module-settings.groups', [GcNumbering::class]);

    $page = Livewire::test(SettingsPage::class)->set('data.format', 'N')->call('save');

    expect(json_encode($page->effects['dispatches'] ?? []))->toContain('Numbering saved.');
});

// ── Something drawn from the live state ────────────────────────────

it('draws the group\'s extension from the state the form holds now', function () {
    config()->set('wire-module-settings.groups', [GcNumbering::class]);

    Livewire::test(SettingsPage::class)
        ->assertDontSee('data-testid="settings-extension"', escape: false)
        ->set('data.format', 'OF')
        ->assertSee('Preview: OF-001');
});

// ── One screen, several storage groups ─────────────────────────────

it('reads a spanning group from every storage group, keeping a default nobody stored', function () {
    config()->set('wire-module-settings.groups', [GcReports::class]);
    Settings::set('interval', 30, 'timers');

    Livewire::test(SettingsPage::class)
        ->assertSet('data.interval', 30)
        ->assertSet('data.grace', 5)
        ->assertSet('data.format', 'decimal');
});

it('writes each key to the storage group it is listed under', function () {
    config()->set('wire-module-settings.groups', [GcReports::class]);

    Livewire::test(SettingsPage::class)
        ->set('data', ['format' => 'time', 'interval' => '20', 'grace' => '3'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::query()->orderBy('key')->get(['group', 'key'])->map(fn ($s) => $s->group.'.'.$s->key)->all())
        ->toBe(['reports.format', 'timers.grace', 'timers.interval']);
});

it('writes several storage groups in one transaction, or none of them', function () {
    Setting::saving(function (Setting $setting): void {
        if ($setting->getAttribute('key') === 'boom') {
            throw new RuntimeException('nope');
        }
    });

    expect(fn () => Settings::fillMany(['reports' => ['format' => 'time'], 'timers' => ['boom' => 1]]))
        ->toThrow(RuntimeException::class);

    expect(Setting::query()->count())->toBe(0);
});

it('announces each storage group it wrote, and none it did not', function () {
    Event::fake([SettingsSaved::class]);

    Settings::fillMany(['reports' => ['format' => 'time'], 'timers' => ['interval' => 20], 'empty' => []]);

    Event::assertDispatchedTimes(SettingsSaved::class, 2);
    Event::assertDispatched(SettingsSaved::class, fn (SettingsSaved $e): bool => $e->group === 'timers' && $e->values === ['interval' => 20]);
});

it('writes nothing and announces nothing for nothing', function () {
    Event::fake([SettingsSaved::class]);

    Settings::fillMany(['reports' => []]);

    Event::assertNotDispatched(SettingsSaved::class);
});

// ── A screen of its own ────────────────────────────────────────────

it('draws the group\'s own component instead of the form, and saves nothing for it', function () {
    config()->set('wire-module-settings.groups', [GcScreenGroup::class]);

    $page = Livewire::test(SettingsPage::class)
        ->assertSee('own screen for calculator')
        ->assertDontSee('data-testid="settings-form"', escape: false);

    $page->call('save')->assertStatus(404);
});

// ── The screen switched off, and a page of the application's own ───

it('registers no screen, no route key and no menu heading when the screen is off', function () {
    config()->set('wire-module-settings.screen', false);

    $module = new SettingsModule;

    expect($module->resources())->toBe([])
        ->and($module->navigation())->toBeNull()
        ->and(SettingsModule::screen())->toBeFalse();
});

it('keeps the screen by default', function () {
    expect((new SettingsModule)->resources())->not->toBe([])
        ->and(app(PluginManager::class)->has('settings'))->toBeTrue()
        ->and(app(ResourceRegistry::class)->has('settings'))->toBeTrue();
});

it('switches groups on the page itself when the module routes none', function () {
    config()->set('wire-module-settings.screen', false);
    config()->set('wire-module-settings.groups', [GcShift::class, GcNumbering::class]);

    Livewire::withQueryParams(['group' => 'numbering'])
        ->test(SettingsPage::class)
        ->assertSet('group', 'numbering')
        ->assertSee('?group=shift', escape: false);
});

it('draws the switcher as tabs when told to', function () {
    config()->set('wire-module-settings.groups', [GcShift::class, GcNumbering::class]);
    config()->set('wire-module-settings.switcher', 'tabs');

    Livewire::test(SettingsPage::class)
        ->assertSee('data-switcher="tabs"', escape: false)
        ->assertSee('role="tablist"', escape: false)
        ->assertSee('aria-selected="true"', escape: false);
});

it('refuses a switcher it does not know, rather than drawing links', function () {
    config()->set('wire-module-settings.groups', [GcShift::class, GcNumbering::class]);
    config()->set('wire-module-settings.switcher', 'pills');

    // Rendering wraps it in a ViewException; the cause is what names the value.
    try {
        Livewire::test(SettingsPage::class);
        $thrown = null;
    } catch (Throwable $e) {
        $thrown = $e instanceof SettingsScreenException ? $e : $e->getPrevious();
    }

    expect($thrown)->toBeInstanceOf(SettingsScreenException::class)
        ->and($thrown->getMessage())->toContain("'pills'");
});

// ── Layout ─────────────────────────────────────────────────────────

it('caps the screen to a configured width and places the save button', function (?string $width, string $align, ?string $class, string $justify) {
    config()->set('wire-module-settings.groups', [GcShift::class]);
    config()->set('wire-module-settings.width', $width);
    config()->set('wire-module-settings.actions_alignment', $align);

    $html = Livewire::test(SettingsPage::class)->html();

    expect($html)->toContain($justify);
    $class === null
        ? expect($html)->not->toContain('mx-auto w-full')
        : expect($html)->toContain('mx-auto w-full '.$class);
})->with([
    'full width, left' => [null, 'left', null, 'justify-start'],
    '2xl, right' => ['2xl', 'right', 'max-w-2xl', 'justify-end'],
]);

it('refuses a width it does not know', function (mixed $width) {
    config()->set('wire-module-settings.groups', [GcShift::class]);
    config()->set('wire-module-settings.width', $width);

    try {
        Livewire::test(SettingsPage::class);
        $thrown = null;
    } catch (Throwable $e) {
        $thrown = $e instanceof SettingsScreenException ? $e : $e->getPrevious();
    }

    expect($thrown)->toBeInstanceOf(SettingsScreenException::class)
        ->and($thrown->getMessage())->toContain('wire-module-settings.width');
})->with(['an unknown token' => 'huge', 'not a string' => 42]);
