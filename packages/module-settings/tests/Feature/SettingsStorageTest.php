<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;
use NyonCode\WireModuleSettings\Events\SettingsSaved;
use NyonCode\WireModuleSettings\Models\Setting;
use NyonCode\WireModuleSettings\Support\Settings;
use NyonCode\WireModuleSettings\WireModuleSettingsServiceProvider;

/*
 * Storage: the cache, the write, and the two ways each of them used to be wrong.
 */

function ssTable(): void
{
    Schema::create('wire_settings', function (Blueprint $table) {
        $table->id();
        $table->string('group')->default('general');
        $table->string('key');
        $table->json('value')->nullable();
        $table->timestamps();
        $table->unique(['group', 'key']);
    });
}

/** A row written behind the model's back, so nothing invalidates anything. */
function ssRawWrite(string $key, mixed $value, string $group = 'branding'): void
{
    DB::table('wire_settings')->insert([
        'group' => $group,
        'key' => $key,
        'value' => json_encode($value),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(fn () => ssTable());

it('does not remember that the table was missing', function () {
    // The bug this replaces: `rememberForever` stored whatever the callback
    // returned, so the empty array a pre-migration read produced *was* the
    // cache — and a fresh install whose deploy touched a setting before
    // `migrate` came up empty and stayed empty until something wrote a value.
    Schema::drop('wire_settings');

    expect(Settings::get('company_name', 'Acme', 'branding'))->toBe('Acme');

    ssTable();
    ssRawWrite('company_name', 'Nyon');

    expect(Settings::get('company_name', 'Acme', 'branding'))->toBe('Nyon');
});

it('drops the cache when a row is written past the support class', function () {
    // A seeder, a console command, tinker — anything that saves the model.
    // Invalidation lives on the model because that is what those writes go
    // through and the support class is not. A query-builder write is another
    // matter; see the test below that pins it.
    Settings::set('company_name', 'Acme', 'branding');

    Setting::query()->where('group', 'branding')->where('key', 'company_name')->first()
        ?->update(['value' => 'Nyon']);

    expect(Settings::get('company_name', null, 'branding'))->toBe('Nyon');
});

it('drops the cache when a row is deleted past the support class', function () {
    Settings::set('company_name', 'Acme', 'branding');

    Setting::query()->where('group', 'branding')->first()?->delete();

    expect(Settings::get('company_name', null, 'branding'))->toBeNull();
});

it('writes a whole group or none of it', function () {
    // The screen edits one group at a time so it never has to say "your branding
    // saved but your mail did not"; a loop of six writes where the fourth fails
    // says exactly that about one group's own fields.
    Setting::saving(function (Setting $setting): void {
        if ($setting->getAttribute('key') === 'boom') {
            throw new RuntimeException('nope');
        }
    });

    expect(fn () => Settings::fill(['company_name' => 'Acme', 'boom' => true], 'branding'))
        ->toThrow(RuntimeException::class);

    expect(Settings::all('branding'))->toBe([]);
});

it('announces a write, so an application can rebuild what it configured', function () {
    Event::fake([SettingsSaved::class]);

    Settings::fill(['company_name' => 'Acme'], 'branding');

    Event::assertDispatched(
        SettingsSaved::class,
        fn (SettingsSaved $event): bool => $event->group === 'branding'
            && $event->values === ['company_name' => 'Acme'],
    );
});

it('says nothing and writes nothing for an empty write', function () {
    Event::fake([SettingsSaved::class]);

    Settings::fill([], 'branding');

    Event::assertNotDispatched(SettingsSaved::class);
    expect(Setting::query()->count())->toBe(0);
});

it('removes one stored value', function () {
    Settings::fill(['company_name' => 'Acme', 'support_email' => 'a@b.test'], 'branding');

    Settings::remove('company_name', 'branding');

    expect(Settings::all('branding'))->toBe(['support_email' => 'a@b.test']);
});

it('clears a whole group', function () {
    Settings::fill(['company_name' => 'Acme'], 'branding');
    Settings::fill(['from' => 'a@b.test'], 'mail');

    Settings::clear('branding');

    expect(Settings::all('branding'))->toBe([])
        ->and(Settings::all('mail'))->toBe(['from' => 'a@b.test']);
});

/** A group with a declared default, so a removal has something to fall back to. */
class SsMailWithDefaults implements ProvidesSettingsDefaults, SettingsGroup
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
        return [TextInput::make('host')];
    }

    public static function defaults(): array
    {
        return ['host' => 'smtp.default'];
    }
}

it('announces a removal, with what answers for the key now', function () {
    // A listener that rebuilds the mail transport when the `mail` group is
    // written heard the custom host being set and never heard it being removed,
    // so mail kept going to a server nobody had configured any more.
    config()->set('wire-module-settings.groups', [SsMailWithDefaults::class]);

    Settings::fill(['host' => 'smtp.custom', 'port' => 2525], 'mail');

    Event::fake([SettingsSaved::class]);

    Settings::remove('host', 'mail');
    Settings::remove('port', 'mail');

    Event::assertDispatched(
        SettingsSaved::class,
        fn (SettingsSaved $event): bool => $event->group === 'mail' && $event->values === ['host' => 'smtp.default'],
    );
    Event::assertDispatched(
        SettingsSaved::class,
        fn (SettingsSaved $event): bool => $event->group === 'mail' && $event->values === ['port' => null],
    );
});

it('announces a cleared group, key by key', function () {
    config()->set('wire-module-settings.groups', [SsMailWithDefaults::class]);

    Settings::fill(['host' => 'smtp.custom', 'port' => 2525], 'mail');

    Event::fake([SettingsSaved::class]);

    Settings::clear('mail');

    Event::assertDispatchedTimes(SettingsSaved::class, 1);
    Event::assertDispatched(
        SettingsSaved::class,
        fn (SettingsSaved $event): bool => $event->group === 'mail'
            && $event->values === ['host' => 'smtp.default', 'port' => null],
    );
});

it('announces nothing when there was nothing to remove', function () {
    Event::fake([SettingsSaved::class]);

    Settings::remove('host', 'mail');
    Settings::clear('mail');

    Event::assertNotDispatched(SettingsSaved::class);
});

it('keeps answering the cached value after a write the model did not make, until the group is forgotten', function () {
    // The model's events are what clear a group, and a query-builder write fires
    // none. That is the boundary the docs now state rather than hide, with
    // `Settings::forget()` as the thing to call after such a write.
    Settings::set('logo', 'old.png', 'branding');
    Settings::get('logo', null, 'branding');

    Setting::query()->where('group', 'branding')->where('key', 'logo')
        ->update(['value' => json_encode('new.png')]);

    expect(Settings::get('logo', null, 'branding'))->toBe('old.png');

    Settings::forget('branding');

    expect(Settings::get('logo', null, 'branding'))->toBe('new.png');
});

// ─── The table it lives in ───────────────────────────────────────────────────

it('creates the table the configuration names', function () {
    // The model read its table from `wire-module-settings.table` and the
    // migration wrote `wire_settings` whatever it said: every read answered the
    // defaults without a word, and the first write threw.
    config()->set('wire-module-settings.table', 'app_settings');

    (require __DIR__.'/../../database/migrations/create_wire_settings_table.php')->up();

    expect(Schema::hasTable('app_settings'))->toBeTrue();

    Settings::set('logo', 'a.png', 'branding');

    expect(Settings::get('logo', null, 'branding'))->toBe('a.png')
        ->and(DB::table('app_settings')->count())->toBe(1);

    (require __DIR__.'/../../database/migrations/create_wire_settings_table.php')->down();

    expect(Schema::hasTable('app_settings'))->toBeFalse();
});

it('leaves an existing table alone when the migration is handed to it again', function () {
    // The name comes from config, so the installer cannot read it off the
    // source to see the table is already there — an application restored from a
    // schema dump can be handed this migration a second time.
    Settings::set('logo', 'kept.png', 'branding');

    (require __DIR__.'/../../database/migrations/create_wire_settings_table.php')->up();

    expect(Settings::get('logo', null, 'branding'))->toBe('kept.png');
});

it('names the table it uses in the about output', function () {
    config()->set('wire-module-settings.table', 'app_settings');

    $about = (new WireModuleSettingsServiceProvider(app()))->aboutData();

    expect($about['Table'])->toBe('app_settings');
});

it('caches in the store the application named', function () {
    // Settings are read on nearly every request, and on an application whose
    // default store is `database` the read this cache exists to avoid is simply
    // replaced by a different query.
    config()->set('cache.stores.settings_test', ['driver' => 'array']);
    config()->set('wire-module-settings.cache.store', 'settings_test');

    Settings::set('company_name', 'Acme', 'branding');
    Settings::all('branding');

    expect(Cache::store('settings_test')->get('wire-settings.branding'))
        ->toBe(['company_name' => 'Acme']);
});

it('reads the table on every call when caching is turned off', function () {
    config()->set('wire-module-settings.cache.enabled', false);

    Settings::all('branding');
    ssRawWrite('company_name', 'Nyon');

    expect(Settings::get('company_name', null, 'branding'))->toBe('Nyon')
        ->and(Cache::get('wire-settings.branding'))->toBeNull();
});
