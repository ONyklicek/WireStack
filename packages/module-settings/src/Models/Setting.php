<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Models;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleSettings\Support\Settings;

/**
 * One stored setting.
 *
 * The value is JSON, so what went in comes back out: a boolean stays a boolean
 * and an array survives. A settings table with a string column makes every
 * reader cast by hand, and they disagree.
 */
class Setting extends Model
{
    protected $guarded = [];

    /**
     * Any write through the model drops the group's cached values.
     *
     * On the model rather than only in {@see Settings}, because a seeder, a
     * factory or a console command that saves a `Setting` does not go through
     * the support class — and without this it would leave the cache holding the
     * old value for ever. Invalidation belongs where the write happens.
     *
     * **Through the model, and only through it.** This docblock used to claim a
     * data migration writing a row directly was covered too; it is not, and
     * cannot be. `Setting::query()->update()`, `DB::table(...)->insert()` and
     * `truncate()` fire no model event, so nothing here hears them. A write
     * like that has to be followed by {@see Settings::forget()} for its group.
     */
    protected static function booted(): void
    {
        $forget = static function (self $setting): void {
            Settings::forget((string) $setting->getAttribute('group'));
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public function getTable(): string
    {
        return (string) config('wire-module-settings.table', 'wire_settings');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
